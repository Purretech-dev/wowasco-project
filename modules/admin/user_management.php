<?php
require_once __DIR__ . '/../../api/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SESSION['role'] ?? '') !== 'super_admin') {
    echo "<div class='admin-users-page'><div class='alert red'>Access denied. Super admin privileges are required.</div></div>";
    return;
}

function clean($value) {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    if (!tableExists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

if (tableExists($conn, 'users') && columnExists($conn, 'users', 'role')) {
    $conn->query("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'customer'");
}

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'super_admin');
    $password = $_POST['password'] ?? '';

    if ($role !== 'super_admin') {
        $message = "Invalid role selected.";
        $messageType = "red";
    } elseif ($name === '' || $email === '' || $password === '') {
        $message = "Please fill in all required fields.";
        $messageType = "red";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $existing = $check->get_result();

        if ($existing && $existing->num_rows > 0) {
            $message = "A user with this email already exists.";
            $messageType = "red";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, role, customer_id)
                VALUES (?, ?, ?, ?, NULL)
            ");
            $stmt->bind_param("ssss", $name, $email, $hashed, $role);

            if ($stmt->execute()) {
                $message = "User account created successfully.";
                $messageType = "green";
            } else {
                $message = "User account could not be created.";
                $messageType = "red";
            }
        }
    }
}

$users = tableExists($conn, 'users')
    ? $conn->query("SELECT id, name, email, role, customer_id, created_at FROM users ORDER BY id DESC")
    : false;
?>

<div class="admin-users-page">
    <div class="page-head">
        <div>
            <h2>User Management</h2>
            <p>Create super admin users. Customer accounts are self-registered from the customer registration page.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= clean($messageType) ?>"><?= clean($message) ?></div>
    <?php endif; ?>

    <div class="layout">
        <form method="POST" class="form-panel">
            <h3>Create System User</h3>

            <div class="field">
                <label>Full Name</label>
                <input name="name" required>
            </div>

            <div class="field">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>

            <div class="field">
                <label>Role</label>
                <select name="role" required>
                    <option value="super_admin">Super Admin</option>
                </select>
                <small>Customers should register themselves from the registration page.</small>
            </div>

            <div class="field">
                <label>Temporary Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" name="create_user">Create User</button>
        </form>

        <div class="table-panel">
            <div class="table-head">
                <h3>System Users</h3>
                <a href="auth/register.php" target="_blank">Customer Registration</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Customer ID</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php while ($u = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= clean($u['name']) ?></td>
                                    <td><?= clean($u['email']) ?></td>
                                    <td><span class="role-pill"><?= clean($u['role']) ?></span></td>
                                    <td><?= clean($u['customer_id'] ?: 'N/A') ?></td>
                                    <td><?= clean($u['created_at'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.admin-users-page{margin-left:260px;margin-top:85px;padding:24px;background:#f4f7fb;min-height:calc(100vh - 140px);font-family:"Segoe UI",Arial,sans-serif;color:#1e293b}
.page-head,.form-panel,.table-panel{background:#fff;border:1px solid #dbe3ee;border-radius:10px;box-shadow:0 4px 14px rgba(15,23,42,0.04)}
.page-head{border-left:5px solid #0a2a43;padding:18px 20px;margin-bottom:18px}
.page-head h2,h3{margin:0;color:#0a2a43}
.page-head p{margin:6px 0 0;color:#64748b;font-size:14px}
.layout{display:grid;grid-template-columns:340px 1fr;gap:18px}
.form-panel,.table-panel{padding:18px}
h3{margin-bottom:15px}
.field{margin-bottom:14px}
.field label{display:block;color:#334155;font-size:13px;font-weight:700;margin-bottom:6px}
.field input,.field select{width:100%;border:1px solid #dbe3ee;border-radius:9px;padding:11px 12px}
.field small{display:block;color:#64748b;font-size:12px;margin-top:6px;line-height:1.4}
button{width:100%;border:none;background:#0a2a43;color:#fff;border-radius:9px;padding:12px 14px;font-weight:800;cursor:pointer}
.alert{border-left:4px solid;border-radius:10px;padding:12px 14px;margin-bottom:18px;background:#fff;font-size:13px}
.alert.green{background:#ecfdf3;color:#166534;border-left-color:#22c55e}
.alert.red{background:#fef2f2;color:#991b1b;border-left-color:#dc2626}
.table-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
.table-head a{color:#0a2a43;background:#f4c542;padding:8px 11px;border-radius:8px;font-size:12px;font-weight:800;text-decoration:none}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#0a2a43;color:#fff;text-align:left;padding:11px 10px}
td{border-bottom:1px solid #edf2f7;padding:11px 10px;color:#475569}
.role-pill{display:inline-block;background:#eff6ff;color:#1e3a8a;padding:5px 9px;border-radius:999px;font-weight:800;font-size:12px}
@media(max-width:1000px){.admin-users-page{margin-left:0}.layout{grid-template-columns:1fr}}
</style>
