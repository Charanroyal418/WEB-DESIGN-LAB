<?php
// ================================================================
// EngineersHub — Database & Core Configuration
// ================================================================
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    'your_password');  // ← change this
define('DB_NAME',    'engineershub');
define('DB_CHARSET', 'utf8mb4');
define('SITE_NAME',  'EngineersHub');

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES   => false]
        );
    } catch (PDOException $e) {
        die(json_encode(['error' => 'DB connection failed']));
    }
    return $pdo;
}

function jsonOut(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function ok(mixed $data, array $meta = []): void {
    $r = ['success' => true, 'data' => $data];
    if ($meta) $r['meta'] = $meta;
    jsonOut($r);
}

function fail(string $msg, int $code = 400): void {
    jsonOut(['success' => false, 'error' => $msg], $code);
}

function bodyJson(): array { return json_decode(file_get_contents('php://input'), true) ?? []; }
function get(string $k, mixed $d = null): mixed { return $_GET[$k] ?? $d; }
function post(string $k, mixed $d = null): mixed { return $_POST[$k] ?? $d; }
function slug(string $s): string { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $s), '-')); }

function paginate(): array {
    $p = max(1, (int)get('page', 1));
    $n = min(50, max(1, (int)get('per_page', 12)));
    return ['page' => $p, 'limit' => $n, 'offset' => ($p - 1) * $n];
}

function paginationMeta(int $total, array $pg): array {
    return ['total' => $total, 'page' => $pg['page'], 'per_page' => $pg['limit'],
            'total_pages' => (int)ceil($total / $pg['limit'])];
}

// Simple session-based auth helpers
session_start();
function currentSeeker(): ?array  { return $_SESSION['seeker']  ?? null; }
function currentEmployer(): ?array { return $_SESSION['employer'] ?? null; }
function requireSeeker(): array {
    if (!currentSeeker()) fail('Login required', 401);
    return currentSeeker();
}