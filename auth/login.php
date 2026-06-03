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

if (tableExists($conn, 'users') && !columnExists($conn, 'users', 'allowed_pages')) {
    $conn->query("ALTER TABLE users ADD allowed_pages TEXT NULL");
}

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['name'] = $user['name'] ?? '';
        $_SESSION['email'] = $user['email'] ?? '';
        $role = $user['role'] ?? 'customer';
        $role = $role === 'admin' ? 'super_admin' : $role;

        $_SESSION['role'] = $role;
        $_SESSION['customer_id'] = $user['customer_id'] ?? null;
        $_SESSION['allowed_pages'] = [];

        if (!empty($user['allowed_pages'])) {
            $decodedPages = json_decode($user['allowed_pages'], true);
            $_SESSION['allowed_pages'] = is_array($decodedPages) ? $decodedPages : [];
        }

        header("Location: ../dashboard.php");
        exit;
    }

    $error = "Invalid email or password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WOWASCO Login</title>
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
            width:min(460px,100%);
            background:#fff;
            border:1px solid #dbe6ee;
            border-radius:18px;
            overflow:hidden;
            box-shadow:0 24px 70px rgba(10,42,67,0.16);
        }
        .logos{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:18px;
            margin-bottom:22px;
        }
        .logos img{
            width:78px;
            height:78px;
            object-fit:contain;
            background:#fff;
            border-radius:14px;
            padding:8px;
            border:1px solid #dbe3ee;
        }
        .logo-divider{
            width:1px;
            height:58px;
            background:#dbe3ee;
            display:block;
        }
        .form-panel{
            padding:42px;
            display:flex;
            flex-direction:column;
            justify-content:center;
        }
        .form-panel h2{
            margin:0 0 8px;
            color:#0a2a43;
            font-size:28px;
        }
        .form-panel .lead{
            margin:0 0 26px;
            color:#64748b;
            font-size:14px;
            line-height:1.6;
        }
        .alert{
            background:#fef2f2;
            color:#991b1b;
            border-left:4px solid #dc2626;
            padding:12px 14px;
            border-radius:10px;
            margin-bottom:18px;
            font-size:13px;
        }
        .field{
            margin-bottom:16px;
        }
        .field label{
            display:block;
            font-size:13px;
            font-weight:700;
            color:#334155;
            margin-bottom:7px;
        }
        .field input{
            width:100%;
            border:1px solid #dbe3ee;
            border-radius:10px;
            padding:13px 14px;
            font-size:14px;
            outline:none;
            transition:0.2s;
        }
        .field input:focus{
            border-color:#0a2a43;
            box-shadow:0 0 0 3px rgba(10,42,67,0.12);
        }
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
        }
        .switch-link{
            margin-top:18px;
            color:#64748b;
            font-size:13px;
            text-align:center;
        }
        .switch-link a{
            color:#0a2a43;
            font-weight:800;
            text-decoration:none;
        }
        @media(max-width:760px){
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

            <h2>Sign In</h2>
            <p class="lead">Use your registered email and password to continue.</p>

            <?php if ($error): ?>
                <div class="alert"><?= clean($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </div>

                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>

                <button type="submit" class="auth-btn">Login</button>
            </form>

            <div class="switch-link">
                Customer without an account? <a href="register.php">Create customer account</a>
            </div>
        </section>
    </main>
</body>
</html>
