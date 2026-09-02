<?php
// api.php – Updated to use POST for all operations
header('Content-Type: application/json');
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET (fetch all or single)
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($staff ?: null);
    } else {
        $stmt = $pdo->query("SELECT * FROM staff ORDER BY id DESC");
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($staff);
    }
    exit;
}

// Handle POST for all write operations (create, update, delete)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check if it's a DELETE operation
    if (isset($input['_method']) && $input['_method'] === 'DELETE') {
        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
        $success = $stmt->execute([$id]);
        echo json_encode(['success' => $success]);
        exit;
    }
    
    // Check if it's an UPDATE operation
    if (isset($input['_method']) && $input['_method'] === 'PUT') {
        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            exit;
        }
        $sql = "UPDATE staff SET 
                first_name = ?, last_name = ?, email = ?, phone = ?, 
                department = ?, position = ?, hire_date = ?, salary = ?, status = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $input['first_name'],
            $input['last_name'],
            $input['email'],
            $input['phone'],
            $input['department'],
            $input['position'],
            $input['hire_date'],
            $input['salary'],
            $input['status'],
            $id
        ]);
        echo json_encode(['success' => $success]);
        exit;
    }
    
    // CREATE new staff (default POST)
    $sql = "INSERT INTO staff (first_name, last_name, email, phone, department, position, hire_date, salary, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $input['first_name'],
        $input['last_name'],
        $input['email'],
        $input['phone'],
        $input['department'],
        $input['position'],
        $input['hire_date'],
        $input['salary'],
        $input['status'] ?? 'active'
    ]);
    echo json_encode(['success' => $success, 'id' => $pdo->lastInsertId()]);
    exit;
}

// If no method matched
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>