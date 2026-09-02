<?php
/**
 * WORKSHOP SIGNUP SYSTEM - BACKEND
 * Complete backend file with all PHP functions
 */

// ============================================
// DATABASE CONFIGURATION
// ============================================

class Database {
    private $host = "localhost";
    private $db_name = "workshop_db";
    private $username = "root";
    private $password = "";
    private $charset = "utf8mb4";
    public $conn;
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false
    ];

    public function getConnection() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $this->conn = new PDO($dsn, $this->username, $this->password, $this->options);
            return $this->conn;
        } catch (PDOException $exception) {
            error_log("Database Connection Error: " . $exception->getMessage());
            throw new Exception("Unable to connect to database. Please try again later.");
        }
    }

    public function closeConnection() {
        $this->conn = null;
    }

    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollBack() {
        return $this->conn->rollBack();
    }

    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
}

// ============================================
// DATABASE FUNCTIONS
// ============================================

/**
 * Get database connection
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}

/**
 * Get available workshops
 */
function getAvailableWorkshops() {
    try {
        $db = getDB();
        $conn = $db->getConnection();
        $query = "SELECT * FROM workshops 
                  WHERE date >= CURDATE() 
                  AND current_participants < capacity 
                  AND status = 'upcoming' 
                  ORDER BY date ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting workshops: " . $e->getMessage());
        return [];
    }
}

/**
 * Get workshop by ID
 */
function getWorkshopById($id) {
    try {
        $db = getDB();
        $conn = $db->getConnection();
        $query = "SELECT * FROM workshops WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting workshop: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if email is already registered
 */
function isEmailRegistered($workshop_id, $email) {
    try {
        $db = getDB();
        $conn = $db->getConnection();
        $query = "SELECT id FROM participants WHERE workshop_id = ? AND email = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$workshop_id, $email]);
        return $stmt->fetch() ? true : false;
    } catch (Exception $e) {
        error_log("Error checking email: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate phone number
 */
function validatePhone($phone) {
    return preg_match('/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4}$/', $phone);
}

/**
 * Send confirmation email
 */
function sendConfirmationEmail($email, $name, $workshop_title, $registration_code, $workshop) {
    $subject = "Workshop Registration Confirmation";
    
    $message = "Dear {$name},\n\n";
    $message .= "Thank you for registering for \"{$workshop_title}\".\n\n";
    $message .= "Your registration has been confirmed.\n";
    $message .= "Registration Code: {$registration_code}\n\n";
    $message .= "Workshop Details:\n";
    $message .= "-----------------\n";
    $message .= "Title: {$workshop_title}\n";
    $message .= "Date: " . date('F j, Y', strtotime($workshop['date'])) . "\n";
    if (!empty($workshop['time'])) {
        $message .= "Time: " . date('g:i A', strtotime($workshop['time'])) . "\n";
    }
    if (!empty($workshop['location'])) {
        $message .= "Location: {$workshop['location']}\n";
    }
    $message .= "-----------------\n\n";
    $message .= "Please keep this email for your records.\n\n";
    $message .= "If you have any questions, please contact us at support@workshop.com\n\n";
    $message .= "Best regards,\n";
    $message .= "Workshop Team";
    
    $headers = "From: no-reply@workshop.com\r\n";
    $headers .= "Reply-To: support@workshop.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Log email (for development)
    error_log("========== EMAIL SENT ==========\n");
    error_log("To: {$email}\n");
    error_log("Subject: {$subject}\n");
    error_log("Message:\n{$message}\n");
    error_log("===============================\n\n", 3, "email_log.txt");
    
    // Uncomment to send actual email
    // return mail($email, $subject, $message, $headers);
    
    return true; // For development
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================
// FORM PROCESSING
// ============================================

// Check if this is a form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'register') {
    processRegistration();
}

/**
 * Process registration form
 */
function processRegistration() {
    session_start();
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        header('Location: index.php');
        exit();
    }
    
    // Sanitize and validate input
    $workshop_id = filter_input(INPUT_POST, 'workshop_id', FILTER_VALIDATE_INT);
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $company = trim(filter_input(INPUT_POST, 'company', FILTER_SANITIZE_STRING));
    $job_title = trim(filter_input(INPUT_POST, 'job_title', FILTER_SANITIZE_STRING));
    $dietary = trim(filter_input(INPUT_POST, 'dietary_requirements', FILTER_SANITIZE_STRING));
    $special = trim(filter_input(INPUT_POST, 'special_requests', FILTER_SANITIZE_STRING));
    $terms = isset($_POST['terms']) ? true : false;
    
    // Store form data for repopulation
    $_SESSION['form_data'] = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'job_title' => $job_title,
        'dietary_requirements' => $dietary,
        'special_requests' => $special
    ];
    
    // Validation
    $errors = [];
    
    // Required field validation
    if (empty($first_name) || strlen($first_name) < 2) {
        $errors['first_name'] = 'First name is required and must be at least 2 characters';
    }
    if (empty($last_name) || strlen($last_name) < 2) {
        $errors['last_name'] = 'Last name is required and must be at least 2 characters';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required';
    }
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!validatePhone($phone)) {
        $errors['phone'] = 'Please enter a valid phone number (e.g., 123-456-7890)';
    }
    if (!$workshop_id || $workshop_id <= 0) {
        $errors['workshop'] = 'Invalid workshop selection';
    }
    if (!$terms) {
        $errors['terms'] = 'You must agree to the terms and conditions';
    }
    
    // If there are validation errors, redirect back
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header('Location: index.php?workshop_id=' . $workshop_id);
        exit();
    }
    
    try {
        $db = getDB();
        $conn = $db->getConnection();
        
        // Start transaction
        $db->beginTransaction();
        
        // Check if workshop exists and has capacity (with row lock)
        $query = "SELECT id, title, capacity, current_participants, date, time, location, price 
                  FROM workshops 
                  WHERE id = ? AND date >= CURDATE() AND status = 'upcoming'
                  FOR UPDATE";
        $stmt = $conn->prepare($query);
        $stmt->execute([$workshop_id]);
        $workshop = $stmt->fetch();
        
        if (!$workshop) {
            throw new Exception('Workshop not found or is no longer available');
        }
        
        if ($workshop['current_participants'] >= $workshop['capacity']) {
            throw new Exception('This workshop is fully booked. Please select another workshop.');
        }
        
        // Check for duplicate registration
        if (isEmailRegistered($workshop_id, $email)) {
            throw new Exception('You are already registered for this workshop');
        }
        
        // Insert participant
        $query = "INSERT INTO participants (
                    workshop_id, first_name, last_name, email, phone, 
                    company, job_title, dietary_requirements, special_requests
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            $workshop_id, $first_name, $last_name, $email, $phone,
            $company, $job_title, $dietary, $special
        ]);
        $participant_id = $db->lastInsertId();
        
        // Update workshop participant count
        $query = "UPDATE workshops SET current_participants = current_participants + 1 WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$workshop_id]);
        
        // Commit transaction
        $db->commit();
        
        // Get registration code
        $query = "SELECT registration_code FROM participants WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$participant_id]);
        $participant = $stmt->fetch();
        $registration_code = $participant['registration_code'] ?? 'N/A';
        
        // Send confirmation email
        sendConfirmationEmail($email, $first_name, $workshop['title'], $registration_code, $workshop);
        
        // Clear form data
        unset($_SESSION['form_data']);
        
        // Set success message
        $_SESSION['success'] = '✅ Registration successful! You are now registered for "' . $workshop['title'] . '".';
        $_SESSION['registration_code'] = $registration_code;
        
        // Redirect to success page
        header('Location: index.php');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($db)) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header('Location: index.php?workshop_id=' . $workshop_id);
        exit();
    }
}

// ============================================
// SUCCESS PAGE HANDLER
// ============================================

// If success page is requested
if (isset($_GET['page']) && $_GET['page'] === 'success') {
    session_start();
    
    // Redirect if no success message
    if (!isset($_SESSION['success'])) {
        header('Location: index.php');
        exit();
    }
    
    $message = $_SESSION['success'];
    $registration_code = isset($_SESSION['registration_code']) ? $_SESSION['registration_code'] : null;
    unset($_SESSION['success'], $_SESSION['registration_code']);
    
    // Display success page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Successful</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            .container {
                max-width: 600px;
                width: 100%;
                background: white;
                border-radius: 16px;
                padding: 50px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: bounceIn 0.6s ease;
            }
            @keyframes bounceIn {
                0% { opacity: 0; transform: scale(0.8); }
                50% { transform: scale(1.05); }
                100% { opacity: 1; transform: scale(1); }
            }
            .success-icon {
                font-size: 80px;
                color: #48bb78;
                margin-bottom: 20px;
                animation: pulse 1.5s ease infinite;
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            h1 { color: #2d3748; margin-bottom: 15px; font-size: 2rem; }
            .message { color: #4a5568; font-size: 1.1rem; margin-bottom: 20px; line-height: 1.6; }
            .registration-code {
                background: #f7fafc;
                padding: 16px;
                border-radius: 8px;
                margin: 20px 0;
                font-size: 1.2rem;
                font-weight: bold;
                color: #667eea;
                letter-spacing: 2px;
                border: 2px dashed #e2e8f0;
            }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                transition: all 0.3s ease;
                margin: 5px;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            }
            .btn-success { background: #48bb78; }
            .btn-success:hover { box-shadow: 0 8px 25px rgba(72, 187, 120, 0.4); }
            .footer { margin-top: 30px; color: #a0aec0; font-size: 0.9rem; }
            @media (max-width: 600px) {
                .container { padding: 30px 20px; }
                h1 { font-size: 1.5rem; }
                .btn { display: block; margin: 10px 0; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-icon">🎉</div>
            <h1>Registration Successful!</h1>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
            
            <?php if ($registration_code): ?>
                <div class="registration-code">
                    Registration Code: <?php echo htmlspecialchars($registration_code); ?>
                </div>
            <?php endif; ?>
            
            <div style="margin: 20px 0;">
                <a href="index.php" class="btn btn-success">📝 Register for Another Workshop</a>
            </div>
            <div class="footer">
                <p>Thank you for choosing our workshop!</p>
                <p style="margin-top: 4px; font-size: 0.8rem;">
                    A confirmation email has been sent to your email address.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Generate CSRF token for the form
session_start();
$csrf_token = generateCSRFToken();
?>