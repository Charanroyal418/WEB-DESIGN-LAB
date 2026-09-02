<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // ← change this
define('DB_PASS', '');           // ← change this
define('DB_NAME', 'simple_jobs');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode([
            'success' => false,
            'message' => 'DB Error: ' . $conn->connect_error
        ]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
?>