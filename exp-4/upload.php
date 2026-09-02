<?php
// ================================================================
//  upload.php  —  Secure Resume File Upload
//  POST multipart/form-data  { file: File, candidate_id: int }
// ================================================================
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    respond(['error' => 'POST only'], 405);

$candidateId = (int)($_POST['candidate_id'] ?? 0);
if (!$candidateId) respond(['error' => 'candidate_id required'], 422);

// Check candidate exists
$stmt = db()->prepare("SELECT id, resume_file FROM candidates WHERE id = ?");
$stmt->execute([$candidateId]);
$candidate = $stmt->fetch();
if (!$candidate) respond(['error' => 'Candidate not found'], 404);

// Validate upload
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $msgs = [
        UPLOAD_ERR_INI_SIZE  => 'File too large (server limit)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
        UPLOAD_ERR_PARTIAL   => 'Partial upload',
        UPLOAD_ERR_NO_FILE   => 'No file provided',
    ];
    respond(['error' => $msgs[$_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload error'], 400);
}

$file = $_FILES['file'];

if ($file['size'] > MAX_FILE_BYTES)
    respond(['error' => 'File exceeds ' . MAX_FILE_MB . ' MB limit'], 413);

// Server-side MIME check
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, ALLOWED_MIME, true))
    respond(['error' => "Invalid file type: $mime. Allowed: PDF, DOC, DOCX"], 415);

// Create uploads directory
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

// Build safe unique filename
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = sprintf('resume_%d_%s.%s', $candidateId, bin2hex(random_bytes(6)), $ext);
$destPath = UPLOAD_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath))
    respond(['error' => 'Could not save file'], 500);

// Remove old file if it exists
if ($candidate['resume_file']) {
    $old = UPLOAD_DIR . basename($candidate['resume_file']);
    if (file_exists($old)) @unlink($old);
}

$sizeKb = (int)round($file['size'] / 1024);

db()->prepare(
    "UPDATE candidates SET resume_file = ?, resume_size_kb = ?, updated_at = NOW() WHERE id = ?"
)->execute([$filename, $sizeKb, $candidateId]);

logActivity($candidateId, 'resume_uploaded', $candidate['resume_file'] ?? null, $filename);

respond([
    'message'      => 'Resume uploaded successfully',
    'filename'     => $filename,
    'size_kb'      => $sizeKb,
    'download_url' => 'uploads/' . $filename,
]);