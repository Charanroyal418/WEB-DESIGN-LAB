<?php
// ================================================================
//  config.php  —  Database & Application Configuration
//  Edit DB_USER and DB_PASS before deploying
// ================================================================
declare(strict_types=1);

define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'resumerank');
define('DB_USER',    'root');        // ← your MySQL username
define('DB_PASS',    '');            // ← your MySQL password
define('DB_CHARSET', 'utf8mb4');

define('UPLOAD_DIR',     __DIR__ . '/uploads/');
define('MAX_FILE_MB',    5);
define('MAX_FILE_BYTES', MAX_FILE_MB * 1024 * 1024);
define('ALLOWED_MIME',   [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);

// Scoring weights — must sum to 1.0
define('W_SKILLS',     0.50);
define('W_EXPERIENCE', 0.30);
define('W_EDUCATION',  0.20);

// ── PDO singleton ────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT
         . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// ── JSON response ────────────────────────────────────────────
function respond(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── CORS preflight ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// ================================================================
//  SCORING ENGINE
//  Returns: score_skills, score_experience, score_education, score_overall
// ================================================================
function computeScore(array $candidate, array $job): array {

    /* 1 ── Skills (50%)
       Percentage of job-required skills present in candidate's skills */
    $required = array_map('strtolower', (array)($job['required_skills'] ?? []));
    $present  = array_map('strtolower', (array)($candidate['skills']    ?? []));
    $sSkills  = 0;
    if (!empty($required)) {
        $matched = count(array_intersect($required, $present));
        $sSkills = (int) round(($matched / count($required)) * 100);
    }

    /* 2 ── Experience (30%)
       Linear scale up to 2× the job minimum; hard-capped at 100 */
    $minExp  = max(1.0, (float)($job['min_experience'] ?? 1));
    $candExp = (float)($candidate['experience_yrs'] ?? 0);
    $sExp    = (int) round(min($candExp / $minExp, 2.0) * 50);
    $sExp    = min($sExp, 100);

    /* 3 ── Education (20%)
       Keyword-tier heuristic + prestigious-institute bonus */
    $edu  = strtolower((string)($candidate['education'] ?? ''));
    $sEdu = 50; // baseline

    if      (preg_match('/phd|doctorate/i',               $edu)) $sEdu = 100;
    elseif  (preg_match('/mtech|m\.tech|m\.sc|mca|mba/i', $edu)) $sEdu = 90;
    elseif  (preg_match('/btech|b\.tech|b\.e\b|bdes/i',   $edu)) $sEdu = 80;
    elseif  (preg_match('/b\.sc|diploma/i',                $edu)) $sEdu = 65;

    if (preg_match('/\b(iit|iim|nit|nid|bits|iiser)\b/i', $edu))
        $sEdu = min(100, $sEdu + 10); // prestige bonus

    /* 4 ── Weighted overall */
    $overall = (int) round(
        $sSkills * W_SKILLS +
        $sExp    * W_EXPERIENCE +
        $sEdu    * W_EDUCATION
    );

    return [
        'score_skills'     => $sSkills,
        'score_experience' => $sExp,
        'score_education'  => $sEdu,
        'score_overall'    => $overall,
    ];
}

// ── Activity log helper ──────────────────────────────────────
function logActivity(int $candId, string $action, ?string $old, ?string $new): void {
    db()->prepare(
        'INSERT INTO activity_log (candidate_id, action, old_value, new_value)
         VALUES (?, ?, ?, ?)'
    )->execute([$candId, $action, $old, $new]);
}