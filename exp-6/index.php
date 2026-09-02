 <?php
// ============================================
// DATABASE CONFIGURATION & FUNCTIONS
// ============================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Database {
    private $host = "localhost";
    private $db_name = "workshop_db";
    private $username = "root";
    private $password = "";
    private $charset = "utf8mb4";
    public $conn;

    public function getConnection() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $this->conn;
        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }
}

function getAvailableWorkshops() {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $query = "SELECT * FROM workshops WHERE date >= CURDATE() AND current_participants < capacity ORDER BY date";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getWorkshopById($id) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $query = "SELECT * FROM workshops WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function isEmailRegistered($workshop_id, $email) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $query = "SELECT id FROM participants WHERE workshop_id = ? AND email = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$workshop_id, $email]);
        return $stmt->fetch() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

function validatePhone($phone) {
    return preg_match('/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4}$/', $phone);
}

// ============================================
// FORM PROCESSING
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $workshop_id = filter_input(INPUT_POST, 'workshop_id', FILTER_VALIDATE_INT);
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $company = trim(filter_input(INPUT_POST, 'company', FILTER_SANITIZE_STRING));
    $job_title = trim(filter_input(INPUT_POST, 'job_title', FILTER_SANITIZE_STRING));
    $dietary = trim(filter_input(INPUT_POST, 'dietary_requirements', FILTER_SANITIZE_STRING));
    $special = trim(filter_input(INPUT_POST, 'special_requests', FILTER_SANITIZE_STRING));
    
    $errors = [];
    
    if (empty($first_name) || strlen($first_name) < 2) {
        $errors[] = 'First name is required and must be at least 2 characters';
    }
    if (empty($last_name) || strlen($last_name) < 2) {
        $errors[] = 'Last name is required and must be at least 2 characters';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    } elseif (!validatePhone($phone)) {
        $errors[] = 'Please enter a valid phone number';
    }
    if (!$workshop_id || $workshop_id <= 0) {
        $errors[] = 'Invalid workshop selection';
    }
    
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header('Location: index.php?workshop_id=' . $workshop_id);
        exit();
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $conn->beginTransaction();
        
        $query = "SELECT id, title, capacity, current_participants FROM workshops WHERE id = ? AND date >= CURDATE() FOR UPDATE";
        $stmt = $conn->prepare($query);
        $stmt->execute([$workshop_id]);
        $workshop = $stmt->fetch();
        
        if (!$workshop) {
            throw new Exception('Workshop not found');
        }
        if ($workshop['current_participants'] >= $workshop['capacity']) {
            throw new Exception('Workshop is fully booked');
        }
        if (isEmailRegistered($workshop_id, $email)) {
            throw new Exception('You are already registered');
        }
        
        $query = "INSERT INTO participants (workshop_id, first_name, last_name, email, phone, company, job_title, dietary_requirements, special_requests) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->execute([$workshop_id, $first_name, $last_name, $email, $phone, $company, $job_title, $dietary, $special]);
        
        $query = "UPDATE workshops SET current_participants = current_participants + 1 WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$workshop_id]);
        
        $conn->commit();
        
        $_SESSION['success'] = '✅ Registration successful for "' . $workshop['title'] . '"!';
        header('Location: index.php');
        exit();
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header('Location: index.php?workshop_id=' . $workshop_id);
        exit();
    }
}

// ============================================
// PAGE DATA
// ============================================

$workshops = getAvailableWorkshops();
$selected_workshop = null;
if (isset($_GET['workshop_id'])) {
    $selected_workshop = getWorkshopById($_GET['workshop_id']);
}

$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : null;
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['errors']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workshop Registration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        header h1 {
            color: #333;
            font-size: 2.5em;
        }

        header p {
            color: #666;
            font-size: 1.1em;
        }

        .workshop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .workshop-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .workshop-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .workshop-card.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .workshop-card h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .workshop-card .description {
            color: #666;
            margin: 10px 0;
            line-height: 1.5;
        }

        .workshop-card .detail {
            color: #555;
            margin: 6px 0;
        }

        .workshop-card .btn-select {
            display: inline-block;
            margin-top: 15px;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .registration-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            margin-top: 30px;
        }

        .registration-form h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group input.error,
        .form-group textarea.error {
            border-color: #dc3545;
        }

        .error-message {
            color: #dc3545;
            font-size: 13px;
            display: none;
            margin-top: 5px;
        }

        .error-message.show {
            display: block;
        }

        .hint-text {
            color: #6c757d;
            font-size: 13px;
            margin-top: 4px;
        }

        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-select {
            background: #28a745;
            color: white;
        }

        .btn-select:hover {
            background: #218838;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert ul {
            margin: 5px 0 0 20px;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s ease infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            header h1 {
                font-size: 2em;
            }
            .workshop-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .form-actions {
                flex-direction: column;
            }
            .form-actions .btn {
                width: 100%;
                margin: 5px 0;
            }
            .btn-secondary {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🚀 Workshop Registration</h1>
            <p>Sign up for our upcoming workshops and enhance your skills</p>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors && is_array($errors)): ?>
            <div class="alert alert-error">
                ⚠️ Please fix the following errors:
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <h2 style="margin-bottom: 20px; color: #333;">Available Workshops</h2>
        <div class="workshop-grid">
            <?php if (count($workshops) > 0): ?>
                <?php foreach ($workshops as $workshop): ?>
                    <div class="workshop-card <?php echo ($selected_workshop && $selected_workshop['id'] == $workshop['id']) ? 'selected' : ''; ?>">
                        <h3><?php echo htmlspecialchars($workshop['title']); ?></h3>
                        <p class="description"><?php echo htmlspecialchars($workshop['description']); ?></p>
                        <div class="detail">📅 <strong>Date:</strong> <?php echo date('F j, Y', strtotime($workshop['date'])); ?></div>
                        <div class="detail">
                            🎯 <strong>Available spots:</strong> 
                            <?php 
                            $available = $workshop['capacity'] - $workshop['current_participants'];
                            if ($available <= 0) {
                                echo '<span class="badge badge-danger">Fully Booked</span>';
                            } elseif ($available <= 3) {
                                echo '<span class="badge badge-warning">' . $available . ' left!</span>';
                            } else {
                                echo $available;
                            }
                            ?>
                        </div>
                        <div class="detail">👥 <strong>Total capacity:</strong> <?php echo $workshop['capacity']; ?></div>
                        <a href="?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-select">
                            <?php echo ($selected_workshop && $selected_workshop['id'] == $workshop['id']) ? '✓ Selected' : 'Select Workshop'; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="grid-column: 1/-1; text-align: center; padding: 40px; color: #666;">
                    No workshops available at the moment. Please check back later.
                </p>
            <?php endif; ?>
        </div>

        <?php if ($selected_workshop): ?>
            <?php 
            $available_spots = $selected_workshop['capacity'] - $selected_workshop['current_participants'];
            ?>
            <div class="registration-form">
                <h2>📝 Register for: <?php echo htmlspecialchars($selected_workshop['title']); ?></h2>
                
                <?php if ($available_spots > 0): ?>
                    <p style="margin-bottom: 20px; color: #28a745; font-weight: 600;">
                        ✅ <?php echo $available_spots; ?> spots remaining
                    </p>

                    <form id="signupForm" method="POST" novalidate>
                        <input type="hidden" name="action" value="register">
                        <input type="hidden" name="workshop_id" value="<?php echo $selected_workshop['id']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
                                <span class="error-message" id="first_name_error"></span>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" required>
                                <span class="error-message" id="last_name_error"></span>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" placeholder="you@example.com" required>
                                <span class="error-message" id="email_error"></span>
                                <div class="hint-text">We'll send confirmation to this email</div>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="required">*</span></label>
                                <input type="tel" id="phone" name="phone" placeholder="(123) 456-7890" required>
                                <span class="error-message" id="phone_error"></span>
                                <div class="hint-text">Format: (123) 456-7890</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="company">Company</label>
                                <input type="text" id="company" name="company" placeholder="Your company name">
                            </div>
                            <div class="form-group">
                                <label for="job_title">Job Title</label>
                                <input type="text" id="job_title" name="job_title" placeholder="Your job title">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="dietary_requirements">Dietary Requirements</label>
                            <textarea id="dietary_requirements" name="dietary_requirements" rows="2" placeholder="Any dietary restrictions or preferences"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="special_requests">Special Requests</label>
                            <textarea id="special_requests" name="special_requests" rows="2" placeholder="Any special accommodations or requests"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" id="submitBtn" class="btn btn-primary">
                                <span id="submitText">✓ Register Now</span>
                                <span id="submitLoading" style="display: none;">
                                    <span class="spinner"></span> Registering...
                                </span>
                            </button>
                            <button type="reset" class="btn btn-secondary">Clear All</button>
                        </div>

                        <p style="margin-top: 15px; color: #6c757d; font-size: 14px;">
                            <span class="required">*</span> Required fields
                        </p>
                    </form>
                <?php else: ?>
                    <div class="alert alert-error">
                        😔 This workshop is fully booked. Please select another workshop.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('signupForm');
            if (!form) return;

            const firstName = document.getElementById('first_name');
            const lastName = document.getElementById('last_name');
            const email = document.getElementById('email');
            const phone = document.getElementById('phone');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitLoading = document.getElementById('submitLoading');

            function validateField(field, errorId, validator) {
                const errorEl = document.getElementById(errorId);
                const value = field.value;
                const error = validator(value);
                
                if (error) {
                    field.classList.add('error');
                    errorEl.textContent = error;
                    errorEl.classList.add('show');
                    return false;
                } else {
                    field.classList.remove('error');
                    errorEl.classList.remove('show');
                    return true;
                }
            }

            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            function isValidPhone(phone) {
                return /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4}$/.test(phone);
            }

            function validateAll() {
                let isValid = true;
                
                if (!validateField(firstName, 'first_name_error', v => {
                    if (!v.trim()) return 'First name is required';
                    if (v.trim().length < 2) return 'First name must be at least 2 characters';
                    return null;
                })) isValid = false;
                
                if (!validateField(lastName, 'last_name_error', v => {
                    if (!v.trim()) return 'Last name is required';
                    if (v.trim().length < 2) return 'Last name must be at least 2 characters';
                    return null;
                })) isValid = false;
                
                if (!validateField(email, 'email_error', v => {
                    if (!v.trim()) return 'Email is required';
                    if (!isValidEmail(v)) return 'Please enter a valid email address';
                    return null;
                })) isValid = false;
                
                if (!validateField(phone, 'phone_error', v => {
                    if (!v.trim()) return 'Phone number is required';
                    if (!isValidPhone(v)) return 'Please enter a valid phone number';
                    return null;
                })) isValid = false;
                
                return isValid;
            }

            [firstName, lastName, email, phone].forEach(field => {
                field.addEventListener('blur', function() {
                    validateAll();
                });
                field.addEventListener('input', function() {
                    if (this.classList.contains('error')) {
                        validateAll();
                    }
                });
            });

            phone.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length > 0) {
                    if (value.length <= 3) {
                        value = '(' + value;
                    } else if (value.length <= 6) {
                        value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
                    } else {
                        value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
                    }
                }
                this.value = value;
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!validateAll()) {
                    const firstError = form.querySelector('.error');
                    if (firstError) {
                        firstError.focus();
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }

                submitBtn.disabled = true;
                submitText.style.display = 'none';
                submitLoading.style.display = 'inline';
                submitBtn.style.opacity = '0.7';
                this.submit();
            });
        });
    </script>
</body>
</html>