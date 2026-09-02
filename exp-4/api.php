<?php
// ================================================================
//  api.php  —  REST API
//  Route via:  api.php?r=candidates|jobs|stats|shortlist|activity
// ================================================================
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$r      = trim($_GET['r'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$body   = (array)(json_decode(file_get_contents('php://input'), true) ?? []);

match ($r) {
    'candidates' => handleCandidates($method, $id, $body),
    'jobs'       => handleJobs($method, $id, $body),
    'stats'      => handleStats(),
    'shortlist'  => handleAutoShortlist($body),
    'activity'   => handleActivity($id),
    default      => respond(['error' => 'Unknown route'], 404),
};

// ================================================================
//  CANDIDATES
// ================================================================
function handleCandidates(string $method, ?int $id, array $body): void {
    match ($method) {
        'GET'    => $id ? getCandidate($id) : listCandidates(),
        'POST'   => createCandidate($body),
        'PUT'    => updateCandidate($id, $body),
        'DELETE' => deleteCandidate($id),
        default  => respond(['error' => 'Method not allowed'], 405),
    };
}

function listCandidates(): never {
    // Filters
    $jobId    = isset($_GET['job_id'])   ? (int)$_GET['job_id']  : null;
    $status   = $_GET['status']   ?? null;
    $search   = $_GET['search']   ?? null;
    $sort     = $_GET['sort']     ?? 'score_overall';
    $dir      = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $page     = max(1, (int)($_GET['page']     ?? 1));
    $perPage  = min(100, max(5, (int)($_GET['per_page'] ?? 20)));
    $minScore = isset($_GET['min_score']) ? (int)$_GET['min_score'] : null;

    $allowed = ['score_overall','score_skills','score_experience',
                'score_education','experience_yrs','full_name','applied_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'score_overall';

    $where = ['1=1'];
    $params = [];
    if ($jobId)             { $where[] = 'c.job_id = ?';          $params[] = $jobId; }
    if ($status)            { $where[] = 'c.status = ?';           $params[] = $status; }
    if ($minScore !== null) { $where[] = 'c.score_overall >= ?';   $params[] = $minScore; }
    if ($search) {
        $where[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.current_role LIKE ?)';
        $like = "%$search%";
        array_push($params, $like, $like, $like);
    }

    $w   = implode(' AND ', $where);
    $off = ($page - 1) * $perPage;

    $total = db()->prepare("SELECT COUNT(*) FROM candidates c WHERE $w");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();

    $stmt = db()->prepare("
        SELECT c.*,
               j.title           AS job_title,
               j.department      AS job_department,
               j.required_skills AS job_required_skills
        FROM candidates c
        JOIN jobs j ON j.id = c.job_id
        WHERE $w
        ORDER BY c.`$sort` $dir
        LIMIT $perPage OFFSET $off
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['skills']              = json_decode($r['skills']              ?? '[]', true) ?: [];
        $r['job_required_skills'] = json_decode($r['job_required_skills'] ?? '[]', true) ?: [];
        $r['score_label']         = scoreLabel((int)$r['score_overall']);
        $r['match_percent']       = !empty($r['job_required_skills'])
            ? (int)round(count(array_intersect(
                  array_map('strtolower', $r['skills']),
                  array_map('strtolower', $r['job_required_skills'])
              )) / count($r['job_required_skills']) * 100)
            : 0;
    }

    respond([
        'data' => $rows,
        'meta' => [
            'total'     => $totalCount,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($totalCount / $perPage),
        ],
    ]);
}

function getCandidate(int $id): never {
    $stmt = db()->prepare("
        SELECT c.*, j.title AS job_title, j.department AS job_department,
               j.required_skills AS job_required_skills
        FROM candidates c JOIN jobs j ON j.id = c.job_id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Not found'], 404);

    $row['skills']              = json_decode($row['skills']              ?? '[]', true) ?: [];
    $row['job_required_skills'] = json_decode($row['job_required_skills'] ?? '[]', true) ?: [];

    $logs = db()->prepare(
        "SELECT * FROM activity_log WHERE candidate_id = ? ORDER BY created_at DESC LIMIT 20"
    );
    $logs->execute([$id]);
    $row['activity'] = $logs->fetchAll();

    respond($row);
}

function createCandidate(array $b): never {
    // Validate required fields
    foreach (['job_id', 'full_name', 'email'] as $f) {
        if (empty($b[$f])) respond(['error' => "Field '$f' is required"], 422);
    }

    // Fetch job for scoring
    $jobStmt = db()->prepare("SELECT * FROM jobs WHERE id = ?");
    $jobStmt->execute([$b['job_id']]);
    $job = $jobStmt->fetch();
    if (!$job) respond(['error' => 'Job not found'], 404);
    $job['required_skills'] = json_decode($job['required_skills'] ?? '[]', true) ?: [];

    // Parse skills
    $skills = is_array($b['skills'] ?? null)
        ? $b['skills']
        : array_filter(array_map('trim', explode(',', $b['skills'] ?? '')));

    // Compute scores
    $b['skills'] = $skills;
    $scores = computeScore($b, $job);

    $stmt = db()->prepare("
        INSERT INTO candidates
          (job_id, full_name, email, phone, location, linkedin_url,
           current_role, experience_yrs, education, skills,
           score_skills, score_experience, score_education, score_overall, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $b['job_id'],            $b['full_name'],      $b['email'],
        $b['phone']       ?? null, $b['location'] ?? null,
        $b['linkedin_url']?? null,
        $b['current_role']?? null, (float)($b['experience_yrs'] ?? 0),
        $b['education']   ?? null, json_encode(array_values($skills)),
        $scores['score_skills'], $scores['score_experience'],
        $scores['score_education'], $scores['score_overall'],
        $b['notes']       ?? null,
    ]);

    $newId = (int)db()->lastInsertId();
    logActivity($newId, 'created', null, 'new');

    respond(['message' => 'Candidate created', 'id' => $newId, 'scores' => $scores], 201);
}

function updateCandidate(?int $id, array $b): never {
    if (!$id) respond(['error' => 'ID required'], 400);

    $existing = db()->prepare("SELECT * FROM candidates WHERE id = ?");
    $existing->execute([$id]);
    $row = $existing->fetch();
    if (!$row) respond(['error' => 'Not found'], 404);

    // Status change
    if (isset($b['status']) && $b['status'] !== $row['status']) {
        $valid = ['new','reviewing','shortlisted','interviewing','offered','hired','rejected'];
        if (!in_array($b['status'], $valid, true))
            respond(['error' => 'Invalid status'], 422);
        db()->prepare("UPDATE candidates SET status = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$b['status'], $id]);
        logActivity($id, 'status_change', $row['status'], $b['status']);
    }

    // Notes
    if (array_key_exists('notes', $b)) {
        db()->prepare("UPDATE candidates SET notes = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$b['notes'], $id]);
    }

    // Re-score if skills / experience updated
    if (isset($b['skills']) || isset($b['experience_yrs'])) {
        $skills = is_array($b['skills'] ?? null)
            ? $b['skills']
            : array_filter(array_map('trim', explode(',', $b['skills'] ?? '')));
        $merged = array_merge($row, ['skills' => $skills, 'experience_yrs' => $b['experience_yrs'] ?? $row['experience_yrs']]);

        $job = db()->prepare("SELECT * FROM jobs WHERE id = ?");
        $job->execute([$row['job_id']]);
        $jobRow = $job->fetch();
        $jobRow['required_skills'] = json_decode($jobRow['required_skills'] ?? '[]', true) ?: [];
        $scores = computeScore($merged, $jobRow);

        db()->prepare("
            UPDATE candidates
            SET skills = ?, experience_yrs = ?,
                score_skills = ?, score_experience = ?,
                score_education = ?, score_overall = ?,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([
            json_encode(array_values($skills)),
            $merged['experience_yrs'],
            $scores['score_skills'], $scores['score_experience'],
            $scores['score_education'], $scores['score_overall'],
            $id,
        ]);
        logActivity($id, 'rescored', (string)$row['score_overall'], (string)$scores['score_overall']);
    }

    respond(['message' => 'Updated', 'id' => $id]);
}

function deleteCandidate(?int $id): never {
    if (!$id) respond(['error' => 'ID required'], 400);
    db()->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
    respond(['message' => 'Deleted']);
}

// ================================================================
//  JOBS
// ================================================================
function handleJobs(string $method, ?int $id, array $body): void {
    match ($method) {
        'GET'    => $id ? getJob($id) : listJobs(),
        'POST'   => createJob($body),
        default  => respond(['error' => 'Method not allowed'], 405),
    };
}

function listJobs(): never {
    $stmt = db()->query("
        SELECT j.*,
               COUNT(c.id)                         AS applicant_count,
               SUM(c.status = 'shortlisted')        AS shortlisted_count,
               SUM(c.status = 'hired')              AS hired_count,
               ROUND(AVG(c.score_overall), 1)       AS avg_score,
               MAX(c.score_overall)                 AS top_score
        FROM jobs j
        LEFT JOIN candidates c ON c.job_id = j.id
        GROUP BY j.id
        ORDER BY j.created_at DESC
    ");
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r)
        $r['required_skills'] = json_decode($r['required_skills'] ?? '[]', true) ?: [];
    respond($rows);
}

function getJob(int $id): never {
    $stmt = db()->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Not found'], 404);
    $row['required_skills'] = json_decode($row['required_skills'] ?? '[]', true) ?: [];
    respond($row);
}

function createJob(array $b): never {
    db()->prepare("
        INSERT INTO jobs (title, department, location, description, required_skills, min_experience, status)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([
        $b['title']          ?? 'Untitled',
        $b['department']     ?? 'General',
        $b['location']       ?? null,
        $b['description']    ?? null,
        json_encode(is_array($b['required_skills'] ?? null) ? $b['required_skills'] : []),
        (float)($b['min_experience'] ?? 0),
        $b['status']         ?? 'open',
    ]);
    respond(['message' => 'Job created', 'id' => (int)db()->lastInsertId()], 201);
}

// ================================================================
//  STATS — dashboard aggregates
// ================================================================
function handleStats(): never {
    $overall = db()->query("
        SELECT
            COUNT(*)                             AS total,
            SUM(status = 'new')                  AS total_new,
            SUM(status = 'reviewing')            AS total_reviewing,
            SUM(status = 'shortlisted')          AS total_shortlisted,
            SUM(status = 'interviewing')         AS total_interviewing,
            SUM(status = 'offered')              AS total_offered,
            SUM(status = 'hired')                AS total_hired,
            SUM(status = 'rejected')             AS total_rejected,
            ROUND(AVG(score_overall), 1)         AS avg_score,
            ROUND(AVG(score_skills), 1)          AS avg_skills,
            ROUND(AVG(score_experience), 1)      AS avg_experience,
            ROUND(AVG(score_education), 1)       AS avg_education,
            MAX(score_overall)                   AS top_score
        FROM candidates
    ")->fetch();

    $distribution = db()->query("
        SELECT
            SUM(score_overall >= 90)              AS excellent,
            SUM(score_overall BETWEEN 75 AND 89)  AS good,
            SUM(score_overall BETWEEN 60 AND 74)  AS average,
            SUM(score_overall < 60)               AS poor
        FROM candidates
    ")->fetch();

    $byJob = db()->query("
        SELECT j.id, j.title, j.department,
               COUNT(c.id)                        AS applicants,
               ROUND(AVG(c.score_overall), 1)     AS avg_score,
               SUM(c.status = 'shortlisted')      AS shortlisted
        FROM jobs j LEFT JOIN candidates c ON c.job_id = j.id
        GROUP BY j.id
        ORDER BY applicants DESC
    ")->fetchAll();

    $topCandidates = db()->query("
        SELECT c.id, c.full_name, c.current_role,
               c.score_overall, c.status, j.title AS job_title
        FROM candidates c JOIN jobs j ON j.id = c.job_id
        ORDER BY c.score_overall DESC LIMIT 8
    ")->fetchAll();

    respond(compact('overall', 'distribution', 'byJob', 'topCandidates'));
}

// ================================================================
//  AUTO SHORTLIST
// ================================================================
function handleAutoShortlist(array $b): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        respond(['error' => 'POST required'], 405);

    $jobId    = (int)($b['job_id']    ?? 0);
    $topN     = (int)($b['top_n']     ?? 5);
    $minScore = (int)($b['min_score'] ?? 60);

    if (!$jobId) respond(['error' => 'job_id required'], 422);

    // Reset previous auto-shortlist for this job
    db()->prepare(
        "UPDATE candidates SET status = 'reviewing', updated_at = NOW()
         WHERE job_id = ? AND status = 'shortlisted'"
    )->execute([$jobId]);

    // Pick top N above threshold
    $stmt = db()->prepare("
        SELECT id FROM candidates
        WHERE job_id = ? AND score_overall >= ?
        ORDER BY score_overall DESC LIMIT ?
    ");
    $stmt->execute([$jobId, $minScore, $topN]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $cid) {
        db()->prepare(
            "UPDATE candidates SET status = 'shortlisted', updated_at = NOW() WHERE id = ?"
        )->execute([$cid]);
        logActivity((int)$cid, 'auto_shortlisted', 'reviewing', 'shortlisted');
    }

    respond(['shortlisted' => count($ids), 'candidate_ids' => $ids]);
}

// ================================================================
//  ACTIVITY LOG
// ================================================================
function handleActivity(?int $candidateId): never {
    if ($candidateId) {
        $stmt = db()->prepare(
            "SELECT * FROM activity_log WHERE candidate_id = ? ORDER BY created_at DESC LIMIT 30"
        );
        $stmt->execute([$candidateId]);
    } else {
        $stmt = db()->query("
            SELECT al.*, c.full_name
            FROM activity_log al
            JOIN candidates c ON c.id = al.candidate_id
            ORDER BY al.created_at DESC LIMIT 50
        ");
    }
    respond($stmt->fetchAll());
}

// ── helpers ──────────────────────────────────────────────────
function scoreLabel(int $s): string {
    return match (true) {
        $s >= 90 => 'Excellent',
        $s >= 75 => 'Good',
        $s >= 60 => 'Average',
        default  => 'Poor',
    };
}