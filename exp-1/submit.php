<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── Sanitise & validate inputs ──────────────────────────────────────────────
function clean($val) {
    return htmlspecialchars(strip_tags(trim($val)));
}

$full_name    = clean($_POST['full_name']    ?? '');
$email        = clean($_POST['email']        ?? '');
$phone        = clean($_POST['phone']        ?? '');
$job_type     = clean($_POST['job_type']     ?? '');
$experience   = clean($_POST['experience']   ?? '');
$skills_raw   = $_POST['skills']             ?? [];
$portfolio    = clean($_POST['portfolio']    ?? '');
$cover_letter = clean($_POST['cover_letter'] ?? '');

$allowed_job_types  = ['full-time','part-time','contract','internship','remote'];
$allowed_experience = ['0-1','1-3','3-5','5-10','10+'];
$allowed_skills     = [
    'HTML','CSS','JavaScript','TypeScript','React','Vue','Angular','Node.js',
    'PHP','Python','Java','C#','C++','SQL','MySQL','PostgreSQL','MongoDB',
    'AWS','Docker','Git','Figma','Photoshop','SEO','Data Analysis'
];

$errors = [];

if (!$full_name)                                     $errors[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $errors[] = 'A valid email is required.';
if (!preg_match('/^\+?[\d\s\-]{7,20}$/', $phone))   $errors[] = 'A valid phone number is required.';
if (!in_array($job_type, $allowed_job_types))        $errors[] = 'Please select a valid job type.';
if (!in_array($experience, $allowed_experience))     $errors[] = 'Please select a valid experience range.';
if (empty($skills_raw))                              $errors[] = 'Please select at least one skill.';

// Validate each skill
$skills = [];
foreach ($skills_raw as $s) {
    $s = clean($s);
    if (in_array($s, $allowed_skills)) $skills[] = $s;
}
if (empty($skills)) $errors[] = 'No valid skills selected.';

// ── Resume upload ────────────────────────────────────────────────────────────
$resume_filename = null;
if (!empty($_FILES['resume']['name'])) {
    $upload_dir  = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $orig_name   = basename($_FILES['resume']['name']);
    $ext         = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    $allowed_ext = ['pdf','doc','docx'];
    $max_size    = 5 * 1024 * 1024; // 5 MB

    if (!in_array($ext, $allowed_ext))        $errors[] = 'Resume must be PDF, DOC, or DOCX.';
    elseif ($_FILES['resume']['size'] > $max_size) $errors[] = 'Resume must be under 5 MB.';
    else {
        $safe_name       = uniqid('resume_', true) . '.' . $ext;
        $target          = $upload_dir . $safe_name;
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target)) {
            $resume_filename = $safe_name;
        } else {
            $errors[] = 'Failed to upload resume. Please try again.';
        }
    }
}

// ── Return errors if any ─────────────────────────────────────────────────────
if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── Insert into database ─────────────────────────────────────────────────────
$skills_json = json_encode($skills);

$stmt = $conn->prepare(
    "INSERT INTO applications
        (full_name, email, phone, job_type, experience, skills, portfolio_url, cover_letter, resume_filename)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    'sssssssss',
    $full_name,
    $email,
    $phone,
    $job_type,
    $experience,
    $skills_json,
    $portfolio,
    $cover_letter,
    $resume_filename
);

if ($stmt->execute()) {
    $app_id = $conn->insert_id;
    echo json_encode([
        'success'   => true,
        'message'   => 'Application submitted successfully!',
        'reference' => 'APP-' . str_pad($app_id, 5, '0', STR_PAD_LEFT)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
