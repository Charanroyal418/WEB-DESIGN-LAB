<?php
require_once 'db.php';

// Simple session-based auth — change credentials as needed
session_start();
$admin_user = 'admin';
$admin_pass = 'admin123';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $admin_user && $_POST['password'] === $admin_pass) {
        $_SESSION['admin'] = true;
    } else {
        $login_error = 'Invalid credentials.';
    }
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

// Update status
if (isset($_GET['set_status'], $_GET['id']) && $_SESSION['admin']) {
    $id  = (int)$_GET['id'];
    $st  = $_GET['set_status'];
    $allowed = ['pending','reviewed','shortlisted','rejected'];
    if (in_array($st, $allowed)) {
        $s = $conn->prepare("UPDATE applications SET status=? WHERE id=?");
        $s->bind_param('si', $st, $id);
        $s->execute();
    }
    header('Location: admin.php'); exit;
}

$applications = [];
if ($_SESSION['admin'] ?? false) {
    $result = $conn->query("SELECT * FROM applications ORDER BY submitted_at DESC");
    while ($row = $result->fetch_assoc()) {
        $row['skills'] = json_decode($row['skills'], true);
        $applications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – Job Applications</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Sora:wght@300;600;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#0c0e14;--card:#13161f;--border:#1e2230;--accent:#f0c040;--accent2:#5eeaff;--text:#e8eaf0;--muted:#6b7280;--danger:#ff4f6a;--success:#22c97a;--warn:#f59e0b}
body{background:var(--bg);color:var(--text);font-family:'Sora',sans-serif;min-height:100vh}
a{color:var(--accent2);text-decoration:none}

/* LOGIN */
.login-wrap{display:flex;align-items:center;justify-content:center;height:100vh}
.login-box{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:48px 40px;width:360px;text-align:center}
.login-box h1{font-size:1.6rem;font-weight:800;margin-bottom:8px}
.login-box p{color:var(--muted);font-size:.85rem;margin-bottom:32px}
.login-box input{width:100%;padding:12px 16px;background:#1a1d2a;border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:inherit;font-size:.9rem;margin-bottom:12px;outline:none;transition:border .2s}
.login-box input:focus{border-color:var(--accent)}
.login-box button{width:100%;padding:13px;background:var(--accent);color:#0c0e14;font-family:inherit;font-weight:700;font-size:.95rem;border:none;border-radius:8px;cursor:pointer;margin-top:4px}
.error{color:var(--danger);font-size:.82rem;margin-top:8px}

/* ADMIN LAYOUT */
header{background:var(--card);border-bottom:1px solid var(--border);padding:20px 40px;display:flex;align-items:center;justify-content:space-between}
header h1{font-size:1.3rem;font-weight:800}header span{color:var(--muted);font-size:.82rem}
.logout{font-size:.82rem;color:var(--danger)}
.container{max-width:1300px;margin:40px auto;padding:0 24px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:32px}
.stat{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px 24px}
.stat .num{font-size:2rem;font-weight:800;font-family:'JetBrains Mono',monospace}
.stat .label{color:var(--muted);font-size:.8rem;margin-top:4px}
.stat.pending .num{color:var(--warn)}
.stat.shortlisted .num{color:var(--success)}
.stat.rejected .num{color:var(--danger)}

table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden}
thead th{background:#1a1d2a;padding:14px 16px;text-align:left;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
tbody tr{border-top:1px solid var(--border);transition:background .15s}
tbody tr:hover{background:#1a1d2a}
td{padding:14px 16px;font-size:.85rem;vertical-align:middle}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;font-family:'JetBrains Mono',monospace}
.badge.pending{background:rgba(245,158,11,.15);color:var(--warn)}
.badge.reviewed{background:rgba(94,234,255,.12);color:var(--accent2)}
.badge.shortlisted{background:rgba(34,201,122,.15);color:var(--success)}
.badge.rejected{background:rgba(255,79,106,.12);color:var(--danger)}
.skills-list{display:flex;flex-wrap:wrap;gap:4px}
.skill-tag{background:#1e2230;color:var(--accent2);font-size:.68rem;padding:2px 8px;border-radius:4px;font-family:'JetBrains Mono',monospace}
.actions select{background:#1a1d2a;border:1px solid var(--border);color:var(--text);padding:5px 8px;border-radius:6px;font-size:.78rem;cursor:pointer}
</style>
</head>
<body>
<?php if (!($_SESSION['admin'] ?? false)): ?>
<div class="login-wrap">
  <div class="login-box">
    <h1>🔐 Admin Login</h1>
    <p>Job Applications Dashboard</p>
    <form method="POST">
      <input type="text" name="username" placeholder="Username" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit" name="login">Sign In</button>
      <?php if(isset($login_error)): ?><p class="error"><?= $login_error ?></p><?php endif; ?>
    </form>
  </div>
</div>
<?php else: 
  $total = count($applications);
  $pending     = count(array_filter($applications, fn($a) => $a['status']==='pending'));
  $shortlisted = count(array_filter($applications, fn($a) => $a['status']==='shortlisted'));
  $rejected    = count(array_filter($applications, fn($a) => $a['status']==='rejected'));
?>
<header>
  <h1>📋 Applications Dashboard</h1>
  <span><?= $total ?> total applications &nbsp;|&nbsp; <a class="logout" href="?logout">Logout</a></span>
</header>
<div class="container">
  <div class="stats">
    <div class="stat"><div class="num"><?= $total ?></div><div class="label">Total Applications</div></div>
    <div class="stat pending"><div class="num"><?= $pending ?></div><div class="label">Pending Review</div></div>
    <div class="stat shortlisted"><div class="num"><?= $shortlisted ?></div><div class="label">Shortlisted</div></div>
    <div class="stat rejected"><div class="num"><?= $rejected ?></div><div class="label">Rejected</div></div>
  </div>
  <table>
    <thead><tr>
      <th>#</th><th>Name</th><th>Email</th><th>Job Type</th><th>Experience</th><th>Skills</th><th>Submitted</th><th>Status</th><th>Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($applications as $app): ?>
    <tr>
      <td><code style="color:var(--muted);font-size:.75rem">APP-<?= str_pad($app['id'],5,'0',STR_PAD_LEFT) ?></code></td>
      <td><strong><?= htmlspecialchars($app['full_name']) ?></strong></td>
      <td><?= htmlspecialchars($app['email']) ?></td>
      <td><?= ucfirst($app['job_type']) ?></td>
      <td><?= $app['experience'] ?> yrs</td>
      <td>
        <div class="skills-list">
          <?php foreach(array_slice($app['skills'],0,4) as $sk): ?>
            <span class="skill-tag"><?= $sk ?></span>
          <?php endforeach; ?>
          <?php if(count($app['skills'])>4): ?><span class="skill-tag">+<?= count($app['skills'])-4 ?></span><?php endif; ?>
        </div>
      </td>
      <td style="color:var(--muted);font-size:.78rem"><?= date('d M Y', strtotime($app['submitted_at'])) ?></td>
      <td><span class="badge <?= $app['status'] ?>"><?= $app['status'] ?></span></td>
      <td class="actions">
        <form method="GET" style="display:inline">
          <input type="hidden" name="id" value="<?= $app['id'] ?>">
          <select name="set_status" onchange="this.form.submit()">
            <?php foreach(['pending','reviewed','shortlisted','rejected'] as $st): ?>
              <option value="<?= $st ?>" <?= $app['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($applications)): ?>
      <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:40px">No applications yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
</body>
</html>
