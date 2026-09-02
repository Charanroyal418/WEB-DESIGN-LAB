<?php
require_once '../includes/config.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$action = get('action', 'list');

switch ($action) {

// ── List / Search Jobs ───────────────────────────────────────────
case 'list':
    $pg       = paginate();
    $search   = trim(get('q', ''));
    $location = trim(get('location', ''));
    $type     = get('type', '');
    $locType  = get('location_type', '');
    $expMin   = (int)get('exp_min', 0);
    $expMax   = (int)get('exp_max', 99);
    $salMin   = (int)get('sal_min', 0);
    $featured = get('featured', '');
    $company  = (int)get('company_id', 0);
    $skillFilter = get('skill', '');

    $where  = ["j.status = 'active'"];
    $params = [];

    if ($search) {
        $where[] = "(j.title LIKE :q OR c.name LIKE :q2 OR j.description LIKE :q3 OR j.skills_required LIKE :q4)";
        $params += [':q' => "%$search%", ':q2' => "%$search%", ':q3' => "%$search%", ':q4' => "%$search%"];
    }
    if ($location) { $where[] = "j.location LIKE :loc"; $params[':loc'] = "%$location%"; }
    if ($type)     { $where[] = "j.job_type = :type";   $params[':type'] = $type; }
    if ($locType)  { $where[] = "j.location_type = :lt"; $params[':lt'] = $locType; }
    if ($expMin > 0){ $where[] = "j.experience_min >= :emin"; $params[':emin'] = $expMin; }
    if ($expMax < 99){ $where[] = "j.experience_max <= :emax"; $params[':emax'] = $expMax; }
    if ($salMin > 0){ $where[] = "j.salary_min >= :smin"; $params[':smin'] = $salMin; }
    if ($featured)  { $where[] = "j.is_featured = 1"; }
    if ($company)   { $where[] = "j.company_id = :cid"; $params[':cid'] = $company; }
    if ($skillFilter){ $where[] = "j.skills_required LIKE :skill"; $params[':skill'] = "%$skillFilter%"; }

    $sql = "FROM jobs j JOIN companies c ON c.id = j.company_id WHERE " . implode(' AND ', $where);

    $count = db()->prepare("SELECT COUNT(*) $sql");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $sort = get('sort', 'newest');
    $order = match($sort) {
        'salary_high' => 'j.salary_max DESC',
        'salary_low'  => 'j.salary_min ASC',
        'popular'     => 'j.applications DESC',
        default       => 'j.is_featured DESC, j.is_urgent DESC, j.posted_at DESC'
    };

    $stmt = db()->prepare("SELECT j.id, j.title, j.slug, j.location, j.location_type, j.job_type,
        j.experience_min, j.experience_max, j.salary_min, j.salary_max, j.salary_currency,
        j.skills_required, j.is_featured, j.is_urgent, j.applications, j.views, j.posted_at,
        c.id AS company_id, c.name AS company_name, c.slug AS company_slug,
        c.logo_initial, c.logo_color, c.industry, c.is_verified
        $sql ORDER BY $order LIMIT :lim OFFSET :off");

    $stmt->execute([...$params, ':lim' => $pg['limit'], ':off' => $pg['offset']]);
    $jobs = $stmt->fetchAll();

    foreach ($jobs as &$j) {
        $j['skills_required'] = json_decode($j['skills_required'] ?? '[]', true);
        $j['days_ago'] = max(0, (int)((time() - strtotime($j['posted_at'])) / 86400));
        $j['salary_display'] = $j['salary_min']
            ? '$' . number_format($j['salary_min']/1000) . 'K – $' . number_format($j['salary_max']/1000) . 'K'
            : 'Competitive';
    }

    ok($jobs, paginationMeta($total, $pg));
    break;

// ── Single Job Detail ────────────────────────────────────────────
case 'detail':
    $id   = (int)get('id');
    $slug = get('slug', '');

    $where = $id ? "j.id = :val" : "j.slug = :val";
    $val   = $id ?: $slug;

    $stmt = db()->prepare("SELECT j.*, c.name AS company_name, c.slug AS company_slug,
        c.logo_initial, c.logo_color, c.industry, c.size_range, c.hq_location,
        c.description AS company_description, c.website, c.founded_year, c.is_verified
        FROM jobs j JOIN companies c ON c.id = j.company_id WHERE $where");
    $stmt->execute([':val' => $val]);
    $job = $stmt->fetch();

    if (!$job) fail('Job not found', 404);

    // Increment views
    db()->prepare("UPDATE jobs SET views = views + 1 WHERE id = :id")->execute([':id' => $job['id']]);

    $job['skills_required'] = json_decode($job['skills_required'] ?? '[]', true);
    $job['days_ago'] = max(0, (int)((time() - strtotime($job['posted_at'])) / 86400));
    $job['salary_display'] = $job['salary_min']
        ? '$' . number_format($job['salary_min']/1000) . 'K – $' . number_format($job['salary_max']/1000) . 'K'
        : 'Competitive';

    // Related jobs
    $rel = db()->prepare("SELECT j.id, j.title, j.slug, j.location, j.location_type, j.salary_min,
        j.salary_max, j.posted_at, c.name AS company_name, c.logo_initial, c.logo_color
        FROM jobs j JOIN companies c ON c.id = j.company_id
        WHERE j.status = 'active' AND j.id != :id AND (j.company_id = :cid OR j.department = :dept)
        ORDER BY j.posted_at DESC LIMIT 4");
    $rel->execute([':id' => $job['id'], ':cid' => $job['company_id'], ':dept' => $job['department']]);
    $job['related_jobs'] = $rel->fetchAll();

    ok($job);
    break;

// ── Apply to Job ─────────────────────────────────────────────────
case 'apply':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
    $b = bodyJson();

    $jobId  = (int)($b['job_id'] ?? 0);
    $name   = trim($b['name'] ?? '');
    $email  = trim($b['email'] ?? '');
    $cover  = trim($b['cover_letter'] ?? '');

    if (!$jobId || !$name || !$email) fail('job_id, name and email are required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Invalid email');

    // Check job exists and is active
    $job = db()->prepare("SELECT id, title, applications FROM jobs WHERE id = :id AND status = 'active'");
    $job->execute([':id' => $jobId]);
    $jobRow = $job->fetch();
    if (!$jobRow) fail('Job not found or closed', 404);

    // Get or create seeker
    $seek = db()->prepare("SELECT id FROM seekers WHERE email = :email");
    $seek->execute([':email' => $email]);
    $seekerId = $seek->fetchColumn();

    if (!$seekerId) {
        $ins = db()->prepare("INSERT INTO seekers (full_name, email, password_hash) VALUES (:n,:e,password(:p))");
        $ins->execute([':n' => $name, ':e' => $email, ':p' => bin2hex(random_bytes(8))]);
        $seekerId = db()->lastInsertId();
    }

    // Check duplicate
    $dup = db()->prepare("SELECT id FROM applications WHERE job_id = :j AND seeker_id = :s");
    $dup->execute([':j' => $jobId, ':s' => $seekerId]);
    if ($dup->fetch()) fail('You have already applied for this job', 409);

    // Insert application
    db()->prepare("INSERT INTO applications (job_id, seeker_id, cover_letter) VALUES (:j,:s,:c)")
        ->execute([':j' => $jobId, ':s' => $seekerId, ':c' => $cover]);

    // Update application count
    db()->prepare("UPDATE jobs SET applications = applications + 1 WHERE id = :id")->execute([':id' => $jobId]);

    ok(['message' => 'Application submitted successfully', 'job_title' => $jobRow['title']]);
    break;

// ── Featured Jobs (for homepage) ─────────────────────────────────
case 'featured':
    $stmt = db()->prepare("SELECT j.id, j.title, j.slug, j.location, j.location_type, j.job_type,
        j.salary_min, j.salary_max, j.skills_required, j.is_urgent, j.posted_at,
        c.name AS company_name, c.logo_initial, c.logo_color, c.industry, c.is_verified
        FROM jobs j JOIN companies c ON c.id = j.company_id
        WHERE j.status = 'active' AND j.is_featured = 1
        ORDER BY j.posted_at DESC LIMIT 6");
    $stmt->execute();
    $jobs = $stmt->fetchAll();
    foreach ($jobs as &$j) {
        $j['skills_required'] = json_decode($j['skills_required'] ?? '[]', true);
        $j['salary_display'] = $j['salary_min']
            ? '$' . number_format($j['salary_min']/1000) . 'K – $' . number_format($j['salary_max']/1000) . 'K'
            : 'Competitive';
        $j['days_ago'] = max(0,(int)((time()-strtotime($j['posted_at']))/86400));
    }
    ok($jobs);
    break;

// ── Stats for homepage ────────────────────────────────────────────
case 'stats':
    $stmt = db()->query("SELECT
        (SELECT COUNT(*) FROM jobs   WHERE status='active')           AS active_jobs,
        (SELECT COUNT(*) FROM companies)                              AS companies,
        (SELECT COUNT(*) FROM seekers)                                AS engineers,
        (SELECT COUNT(*) FROM applications)                           AS applications,
        (SELECT COUNT(*) FROM jobs WHERE location_type='remote' AND status='active') AS remote_jobs");
    ok($stmt->fetch());
    break;

// ── Job Categories (department counts) ───────────────────────────
case 'categories':
    $stmt = db()->query("SELECT department, COUNT(*) AS job_count
        FROM jobs WHERE status='active' AND department IS NOT NULL
        GROUP BY department ORDER BY job_count DESC");
    ok($stmt->fetchAll());
    break;

// ── Top Skills ────────────────────────────────────────────────────
case 'skills':
    $stmt = db()->query("SELECT name, category, job_count FROM skills ORDER BY job_count DESC LIMIT 24");
    ok($stmt->fetchAll());
    break;

// ── Companies List ────────────────────────────────────────────────
case 'companies':
    $stmt = db()->query("SELECT c.*, COUNT(j.id) AS open_jobs
        FROM companies c LEFT JOIN jobs j ON j.company_id = c.id AND j.status='active'
        GROUP BY c.id ORDER BY c.is_verified DESC, open_jobs DESC");
    ok($stmt->fetchAll());
    break;

default:
    fail('Unknown action', 404);
}