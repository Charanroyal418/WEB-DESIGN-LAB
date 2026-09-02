<?php
require_once '../includes/config.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$action = get('action', '');

switch ($action) {

// ── Register (Seeker) ─────────────────────────────────────────────
case 'register':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
    $b = bodyJson();
    $name  = trim($b['name'] ?? '');
    $email = trim($b['email'] ?? '');
    $pass  = $b['password'] ?? '';
    $role  = $b['role'] ?? 'seeker'; // 'seeker' or 'employer'

    if (!$name || !$email || !$pass) fail('Name, email and password are required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Invalid email');
    if (strlen($pass) < 6) fail('Password must be at least 6 characters');

    if ($role === 'seeker') {
        // Check duplicate
        $chk = db()->prepare("SELECT id FROM seekers WHERE email = :e");
        $chk->execute([':e' => $email]);
        if ($chk->fetch()) fail('Email already registered', 409);

        $stmt = db()->prepare("INSERT INTO seekers (full_name, email, password_hash, headline)
            VALUES (:n, :e, password(:p), :h)");
        $stmt->execute([':n' => $name, ':e' => $email, ':p' => $pass, ':h' => ($b['headline'] ?? '')]);
        $id = db()->lastInsertId();

        $seeker = ['id' => $id, 'full_name' => $name, 'email' => $email, 'role' => 'seeker'];
        $_SESSION['seeker'] = $seeker;
        ok(['user' => $seeker, 'message' => 'Account created successfully'], 201);
    } else {
        fail('Use employer registration form', 400);
    }
    break;

// ── Login ─────────────────────────────────────────────────────────
case 'login':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
    $b = bodyJson();
    $email = trim($b['email'] ?? '');
    $pass  = $b['password'] ?? '';
    $role  = $b['role'] ?? 'seeker';

    if (!$email || !$pass) fail('Email and password are required');

    if ($role === 'seeker') {
        $stmt = db()->prepare("SELECT id, full_name, email, headline, years_exp, skills, is_open
            FROM seekers WHERE email = :e AND password_hash = password(:p)");
        $stmt->execute([':e' => $email, ':p' => $pass]);
        $user = $stmt->fetch();
        if (!$user) fail('Invalid email or password', 401);

        $user['role'] = 'seeker';
        $user['skills'] = json_decode($user['skills'] ?? '[]', true);
        $_SESSION['seeker'] = $user;
        ok(['user' => $user, 'message' => 'Logged in successfully']);
    } else {
        $stmt = db()->prepare("SELECT e.id, e.full_name, e.email, e.role AS emp_role,
            c.id AS company_id, c.name AS company_name, c.logo_initial, c.logo_color
            FROM employers e JOIN companies c ON c.id = e.company_id
            WHERE e.email = :e AND e.password_hash = password(:p)");
        $stmt->execute([':e' => $email, ':p' => $pass]);
        $user = $stmt->fetch();
        if (!$user) fail('Invalid email or password', 401);

        $user['role'] = 'employer';
        $_SESSION['employer'] = $user;
        ok(['user' => $user, 'message' => 'Logged in successfully']);
    }
    break;

// ── Logout ────────────────────────────────────────────────────────
case 'logout':
    $_SESSION = [];
    session_destroy();
    ok(['message' => 'Logged out']);
    break;

// ── Get current user ──────────────────────────────────────────────
case 'me':
    if ($s = currentSeeker())  ok(['user' => $s]);
    if ($e = currentEmployer()) ok(['user' => $e]);
    fail('Not logged in', 401);
    break;

// ── Get seeker applications ───────────────────────────────────────
case 'my_applications':
    $s = requireSeeker();
    $stmt = db()->prepare("SELECT a.id, a.status, a.applied_at,
        j.title, j.slug, j.location, j.location_type, j.salary_min, j.salary_max,
        c.name AS company_name, c.logo_initial, c.logo_color
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN companies c ON c.id = j.company_id
        WHERE a.seeker_id = :sid
        ORDER BY a.applied_at DESC");
    $stmt->execute([':sid' => $s['id']]);
    ok($stmt->fetchAll());
    break;

// ── Save / Unsave Job ─────────────────────────────────────────────
case 'save_job':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
    $s = requireSeeker();
    $b = bodyJson();
    $jobId = (int)($b['job_id'] ?? 0);
    if (!$jobId) fail('job_id required');

    $chk = db()->prepare("SELECT id FROM saved_jobs WHERE seeker_id = :s AND job_id = :j");
    $chk->execute([':s' => $s['id'], ':j' => $jobId]);
    if ($chk->fetch()) {
        db()->prepare("DELETE FROM saved_jobs WHERE seeker_id = :s AND job_id = :j")
            ->execute([':s' => $s['id'], ':j' => $jobId]);
        ok(['saved' => false, 'message' => 'Job removed from saved list']);
    } else {
        db()->prepare("INSERT INTO saved_jobs (seeker_id, job_id) VALUES (:s, :j)")
            ->execute([':s' => $s['id'], ':j' => $jobId]);
        ok(['saved' => true, 'message' => 'Job saved']);
    }
    break;

// ── Employer: get applications for their jobs ─────────────────────
case 'job_applications':
    $e = currentEmployer();
    if (!$e) fail('Employer login required', 401);
    $jobId = (int)get('job_id', 0);

    $where = $jobId ? "AND j.id = :jid" : "";
    $params = [':cid' => $e['company_id']];
    if ($jobId) $params[':jid'] = $jobId;

    $stmt = db()->prepare("SELECT a.id, a.status, a.applied_at, a.cover_letter,
        s.full_name, s.email, s.headline, s.years_exp,
        j.title AS job_title, j.id AS job_id
        FROM applications a
        JOIN seekers s ON s.id = a.seeker_id
        JOIN jobs j ON j.id = a.job_id
        WHERE j.company_id = :cid $where
        ORDER BY a.applied_at DESC");
    $stmt->execute($params);
    ok($stmt->fetchAll());
    break;

// ── Employer: post a new job ──────────────────────────────────────
case 'post_job':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
    $e = currentEmployer();
    if (!$e) fail('Employer login required', 401);
    $b = bodyJson();

    $title = trim($b['title'] ?? '');
    if (!$title) fail('Job title required');

    $sl = slug($title . '-' . $e['company_name'] . '-' . uniqid());
    $stmt = db()->prepare("INSERT INTO jobs
        (company_id, title, slug, department, location, location_type, job_type,
         experience_min, experience_max, salary_min, salary_max,
         description, requirements, skills_required, status, is_urgent)
        VALUES (:cid,:title,:slug,:dept,:loc,:lt,:jt,:emin,:emax,:smin,:smax,:desc,:req,:skills,:status,:urgent)");

    $stmt->execute([
        ':cid'    => $e['company_id'],
        ':title'  => $title,
        ':slug'   => $sl,
        ':dept'   => $b['department'] ?? null,
        ':loc'    => $b['location'] ?? 'Remote',
        ':lt'     => $b['location_type'] ?? 'remote',
        ':jt'     => $b['job_type'] ?? 'full-time',
        ':emin'   => (int)($b['experience_min'] ?? 0),
        ':emax'   => (int)($b['experience_max'] ?? 5),
        ':smin'   => $b['salary_min'] ? (int)$b['salary_min'] : null,
        ':smax'   => $b['salary_max'] ? (int)$b['salary_max'] : null,
        ':desc'   => $b['description'] ?? '',
        ':req'    => $b['requirements'] ?? '',
        ':skills' => json_encode($b['skills'] ?? []),
        ':status' => $b['status'] ?? 'active',
        ':urgent' => (int)($b['is_urgent'] ?? 0),
    ]);
    ok(['id' => db()->lastInsertId(), 'message' => 'Job posted successfully'], 201);
    break;

// ── Update application status (employer) ─────────────────────────
case 'update_application':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
    $e = currentEmployer();
    if (!$e) fail('Employer login required', 401);
    $b      = bodyJson();
    $appId  = (int)($b['application_id'] ?? 0);
    $status = $b['status'] ?? '';
    $valid  = ['reviewing','shortlisted','interview','offered','rejected'];
    if (!$appId || !in_array($status, $valid)) fail('application_id and valid status required');

    db()->prepare("UPDATE applications SET status = :s WHERE id = :id")->execute([':s' => $status, ':id' => $appId]);
    ok(['message' => 'Application status updated']);
    break;

default:
    fail('Unknown action', 404);
}