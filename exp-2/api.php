<?php
/* ═══════════════════════════════════════════════════════
   api.php  —  Single backend file
   Handles: submit | list | update_status | delete | stats
═══════════════════════════════════════════════════════ */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// ── Read action from GET or POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ══════════════════════════════════════════════
//  ACTION: stats
// ══════════════════════════════════════════════
if ($action === 'stats') {
    $db     = getDB();
    $total  = $db->query("SELECT COUNT(*) AS n FROM applications")->fetch_assoc()['n'];
    $rows   = $db->query("SELECT status, COUNT(*) AS n FROM applications GROUP BY status")->fetch_all(MYSQLI_ASSOC);
    $stats  = ['total' => (int)$total];
    foreach ($rows as $r) $stats[$r['status']] = (int)$r['n'];
    $db->close();
    echo json_encode(['success' => true, 'stats' => $stats]);
    exit;
}

// ══════════════════════════════════════════════
//  ACTION: list
// ══════════════════════════════════════════════
if ($action === 'list') {
    $db       = getDB();
    $search   = trim($_GET['search']   ?? '');
    $fStatus  = trim($_GET['status']   ?? '');
    $fJobType = trim($_GET['jtype']    ?? '');

    $where  = []; $params = []; $types = '';

    if ($search !== '') {
        $where[] = "(full_name LIKE ? OR email LIKE ? OR position LIKE ? OR skills LIKE ?)";
        $s = "%{$search}%";
        array_push($params, $s, $s, $s, $s);
        $types .= 'ssss';
    }
    if ($fStatus  !== '') { $where[] = 'status = ?';   $params[] = $fStatus;  $types .= 's'; }
    if ($fJobType !== '') { $where[] = 'job_type = ?'; $params[] = $fJobType; $types .= 's'; }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql      = "SELECT * FROM applications $whereSQL ORDER BY applied_at DESC";

    if ($params) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $rows = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    $db->close();
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// ══════════════════════════════════════════════
//  ACTION: submit
// ══════════════════════════════════════════════
if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    function clean(string $v): string {
        return htmlspecialchars(strip_tags(trim($v)));
    }

    $full_name  = clean($_POST['full_name']  ?? '');
    $email      = clean($_POST['email']      ?? '');
    $phone      = clean($_POST['phone']      ?? '');
    $position   = clean($_POST['position']   ?? '');
    $job_type   = clean($_POST['job_type']   ?? '');
    $experience = clean($_POST['experience'] ?? '');
    $message    = clean($_POST['message']    ?? '');
    $skills_raw = $_POST['skills']           ?? [];

    // Validate
    $errors = [];
    if (empty($full_name))                          $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($phone))                              $errors[] = 'Phone number is required.';
    if (empty($position))                           $errors[] = 'Position is required.';
    if (empty($job_type))                           $errors[] = 'Job type is required.';
    if (empty($experience))                         $errors[] = 'Experience is required.';
    if (empty($skills_raw))                         $errors[] = 'Select at least one skill.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    // Sanitise skills
    $allowed_skills = [
        'HTML','CSS','JavaScript','TypeScript','React','Vue','Angular',
        'Node.js','PHP','Python','Java','C#','MySQL','PostgreSQL',
        'MongoDB','AWS','Docker','Git','Figma','Photoshop','SQL','Linux',
    ];
    $skills = [];
    foreach ($skills_raw as $s) {
        $s = trim(strip_tags($s));
        if (in_array($s, $allowed_skills)) $skills[] = $s;
    }
    if (empty($skills)) {
        echo json_encode(['success' => false, 'errors' => ['No valid skills selected.']]);
        exit;
    }

    $skills_str = implode(', ', $skills);
    $db         = getDB();
    $stmt       = $db->prepare(
        "INSERT INTO applications
             (full_name, email, phone, position, job_type, experience, skills, message)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('ssssssss',
        $full_name, $email, $phone, $position,
        $job_type, $experience, $skills_str, $message
    );

    if ($stmt->execute()) {
        $ref = 'APP' . str_pad($db->insert_id, 5, '0', STR_PAD_LEFT);
        echo json_encode(['success' => true, 'message' => 'Application submitted!', 'ref' => $ref]);
    } else {
        echo json_encode(['success' => false, 'errors' => ['Database error. Try again.']]);
    }

    $stmt->close();
    $db->close();
    exit;
}

// ══════════════════════════════════════════════
//  ACTION: update_status
// ══════════════════════════════════════════════
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id']     ?? 0);
    $status  = trim($_POST['status']  ?? '');
    $allowed = ['Pending','Reviewed','Shortlisted','Interview','Rejected','Hired'];

    if (!$id || !in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    $db   = getDB();
    $stmt = $db->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    $ok   = $stmt->execute();
    $stmt->close();
    $db->close();

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Status updated.' : 'Update failed.']);
    exit;
}

// ══════════════════════════════════════════════
//  ACTION: delete
// ══════════════════════════════════════════════
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok   = $stmt->execute();
    $stmt->close();
    $db->close();

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Deleted.' : 'Delete failed.']);
    exit;
}

// Unknown action
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
?>