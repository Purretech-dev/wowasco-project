<?php
session_start();
require_once __DIR__ . '/../api/db.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "red";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $existing = $check->get_result();

        if ($existing && $existing->num_rows > 0) {
            $message = "An account with this email already exists.";
            $messageType = "red";
        } else {
            $customerId = null;

            if (tableExists($conn, 'customers')) {
                $columns = ['name','email'];
                $values = [$name,$email];

                if (columnExists($conn, 'customers', 'phone')) {
                    $columns[] = 'phone';
                    $values[] = $phone;
                }

                if (columnExists($conn, 'customers', 'id_number')) {
                    $columns[] = 'id_number';
                    $values[] = $idNumber;
                }

                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $types = str_repeat('s', count($columns));

                $stmt = $conn->prepare("INSERT INTO customers (`" . implode('`,`', $columns) . "`) VALUES ($placeholders)");
                $bindValues = [$types];

                foreach ($values as $key => $value) {
                    $bindValues[] = &$values[$key];
                }

                call_user_func_array([$stmt, 'bind_param'], $bindValues);
                $stmt->execute();
                $customerId = $conn->insert_id;
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 'customer';
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, role, customer_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssi", $name, $email, $hashed, $role, $customerId);

            if ($stmt->execute()) {
                $message = "Customer account created successfully. You can now log in.";
                $messageType = "green";
            } else {
                $message = "Registration failed. Please try again.";
                $messageType = "red";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WOWASCO Customer Registration</title>
    <style>
        *{box-sizing:border-box}
        body{
            margin:0;
            min-height:100vh;
            font-family:"Segoe UI",Arial,sans-serif;
            background:#eef4f8;
            color:#102033;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .auth-shell{
            width:min(620px,100%);
            background:#fff;
            border:1px solid #dbe6ee;
            border-radius:18px;
            overflow:hidden;
            box-shadow:0 24px 70px rgba(10,42,67,0.16);
        }
        .logos{display:flex;align-items:center;justify-content:center;gap:18px;margin-bottom:22px}
        .logos img{
            width:78px;
            height:78px;
            object-fit:contain;
            background:#fff;
            border-radius:14px;
            padding:8px;
            border:1px solid #dbe3ee;
        }
        .logo-divider{width:1px;height:58px;background:#dbe3ee;display:block}
        .form-panel{padding:38px}
        .form-panel h2{margin:0 0 8px;color:#0a2a43;font-size:28px}
        .lead{margin:0 0 24px;color:#64748b;font-size:14px;line-height:1.6}
        .alert{
            border-left:4px solid;
            padding:12px 14px;
            border-radius:10px;
            margin-bottom:18px;
            font-size:13px;
        }
        .alert.green{background:#ecfdf3;color:#166534;border-left-color:#22c55e}
        .alert.red{background:#fef2f2;color:#991b1b;border-left-color:#dc2626}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
        .field.full{grid-column:1/-1}
        .field label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:7px}
        .field input{
            width:100%;
            border:1px solid #dbe3ee;
            border-radius:10px;
            padding:13px 14px;
            font-size:14px;
            outline:none;
        }
        .field input:focus{border-color:#0a2a43;box-shadow:0 0 0 3px rgba(10,42,67,0.12)}
        .auth-btn{
            width:100%;
            border:none;
            background:#0a2a43;
            color:#fff;
            border-radius:10px;
            padding:13px 16px;
            cursor:pointer;
            font-size:14px;
            font-weight:800;
            margin-top:16px;
        }
        .switch-link{margin-top:18px;color:#64748b;font-size:13px;text-align:center}
        .switch-link a{color:#0a2a43;font-weight:800;text-decoration:none}
        @media(max-width:820px){
            .form-grid{grid-template-columns:1fr}
            .form-panel{padding:28px}
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="form-panel">
            <div class="logos">
                <img src="../assets/images/logo1.png" alt="WOWASCO logo one">
                <span class="logo-divider"></span>
                <img src="../assets/images/logo2.png" alt="WOWASCO logo two">
            </div>

            <h2>Create Account</h2>
            <p class="lead">Register as a WOWASCO customer.</p>

            <?php if ($message): ?>
                <div class="alert <?= clean($messageType) ?>"><?= clean($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="field">
                        <label>Full Name</label>
                        <input name="name" value="<?= clean($_POST['name'] ?? '') ?>" required>
                    </div>

                    <div class="field">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?= clean($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="field">
                        <label>Phone Number</label>
                        <input name="phone" value="<?= clean($_POST['phone'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label>ID Number</label>
                        <input name="id_number" value="<?= clean($_POST['id_number'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>

                    <div class="field">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>

                <button type="submit" class="auth-btn">Register Customer Account</button>
            </form>

            <div class="switch-link">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </section>
    </main>
</body>
</html>
