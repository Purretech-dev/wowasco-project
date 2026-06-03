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

function addColumnIfMissing($conn, $table, $column, $definition) {
    if (tableExists($conn, $table) && !columnExists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
    }
}

if (tableExists($conn, 'users') && columnExists($conn, 'users', 'role')) {
    $conn->query("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'customer'");
}

addColumnIfMissing($conn, 'users', 'allowed_pages', 'TEXT NULL');

$roleOptions = [
    'super_admin' => 'Super Admin',
    'meter_officer' => 'Meter Officer',
    'production_officer' => 'Production Officer',
    'asset_officer' => 'Asset Officer',
    'customer_service' => 'Customer Service Officer',
    'zone_officer' => 'Zoning Officer',
    'checker' => 'Checker',
    'approver' => 'Approver / MD',
    'reports_viewer' => 'Reports Viewer'
];

$moduleAccess = [
    'Dashboard' => [
        'modules/home.php' => 'Main Dashboard'
    ],
    'Metering Module' => [
        'modules/metering/meter_management.php' => 'Meter Management',
        'modules/metering/meter_dashboard.php' => 'Meter Dashboard',
        'modules/metering/meter_alerts.php' => 'Meter Alerts'
    ],
    'Production Module' => [
        'modules/production/pumped_volume.php' => 'Pumped Volume',
        'modules/production/production_comparison.php' => 'Production Comparison'
    ],
    'Assets Module' => [
        'modules/Assets/asset_management.php' => 'Asset Management',
        'modules/Assets/asset_maintenance.php' => 'Asset Maintenance'
    ],
    'Customer Relations' => [
        'modules/customer/customer_management.php' => 'Customer Management'
    ],
    'Zoning & GIS' => [
        'modules/zoning/zone_management.php' => 'Zone Management',
        'modules/zoning/gis.php' => 'GIS Module'
    ],
    'Approval Workflow' => [
        'modules/approvals/deactivation_checker.php' => 'Checker Workbench',
        'modules/approvals/deactivation_approver.php' => 'MD Approval'
    ],
    'Advanced Reports' => [
        'modules/reports/md_dashboard.php' => 'Report Dashboard',
        'modules/reports/metering_reports.php' => 'Meter Reports',
        'modules/reports/production_reports.php' => 'Production Reports',
        'modules/reports/asset_reports.php' => 'Asset Reports',
        'modules/reports/customer_reports.php' => 'Customer Reports',
        'modules/reports/zoning_reports.php' => 'Zoning Reports'
    ]
];

$allAccessPages = [];
foreach ($moduleAccess as $pages) {
    $allAccessPages = array_merge($allAccessPages, array_keys($pages));
}

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'super_admin');
    $password = $_POST['password'] ?? '';
    $selectedPages = $_POST['allowed_pages'] ?? [];
    $selectedPages = is_array($selectedPages) ? $selectedPages : [];
    $selectedPages = array_values(array_intersect($selectedPages, $allAccessPages));

    if (!array_key_exists($role, $roleOptions)) {
        $message = "Invalid role selected.";
        $messageType = "red";
    } elseif ($name === '' || $email === '' || $password === '') {
        $message = "Please fill in all required fields.";
        $messageType = "red";
    } elseif ($role !== 'super_admin' && empty($selectedPages)) {
        $message = "Please assign at least one module/page to this limited user.";
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
            $allowedPagesJson = $role === 'super_admin' ? null : json_encode($selectedPages);
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, role, customer_id, allowed_pages)
                VALUES (?, ?, ?, ?, NULL, ?)
            ");
            $stmt->bind_param("sssss", $name, $email, $hashed, $role, $allowedPagesJson);

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
    ? $conn->query("SELECT id, name, email, role, customer_id, allowed_pages, created_at FROM users ORDER BY id DESC")
    : false;
?>

<div class="admin-users-page">
    <div class="page-head">
        <div>
            <h2>User Management</h2>
            <p>Create internal users and assign the exact modules they are allowed to access. Customer accounts remain self-registered.</p>
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
                    <?php foreach ($roleOptions as $value => $label): ?>
                        <option value="<?= clean($value) ?>"><?= clean($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Super Admin receives full access automatically. Limited users must be assigned modules below.</small>
            </div>

            <div class="field">
                <label>Module Access</label>
                <div class="permission-grid">
                    <?php foreach ($moduleAccess as $group => $pages): ?>
                        <div class="permission-group">
                            <strong><?= clean($group) ?></strong>

                            <?php foreach ($pages as $path => $label): ?>
                                <label class="check-row">
                                    <input type="checkbox" name="allowed_pages[]" value="<?= clean($path) ?>">
                                    <span><?= clean($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
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
                            <th>Module Access</th>
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
                                    <td><span class="role-pill"><?= clean($roleOptions[$u['role']] ?? $u['role']) ?></span></td>
                                    <td>
                                        <?php
                                            $userPages = !empty($u['allowed_pages']) ? json_decode($u['allowed_pages'], true) : [];
                                            $userPages = is_array($userPages) ? $userPages : [];
                                            $labels = [];

                                            foreach ($moduleAccess as $pages) {
                                                foreach ($pages as $path => $label) {
                                                    if (in_array($path, $userPages, true)) {
                                                        $labels[] = $label;
                                                    }
                                                }
                                            }
                                        ?>

                                        <?php if (($u['role'] ?? '') === 'super_admin'): ?>
                                            <span class="access-pill full">Full Access</span>
                                        <?php elseif (!empty($labels)): ?>
                                            <div class="access-list"><?= clean(implode(', ', $labels)) ?></div>
                                        <?php else: ?>
                                            <span class="access-pill none">No modules assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= clean($u['customer_id'] ?: 'N/A') ?></td>
                                    <td><?= clean($u['created_at'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No users found.</td></tr>
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
.permission-grid{display:grid;grid-template-columns:1fr;gap:10px;max-height:360px;overflow:auto;border:1px solid #dbe3ee;border-radius:10px;padding:10px;background:#f8fafc}
.permission-group{background:#fff;border:1px solid #e5edf5;border-radius:9px;padding:10px}
.permission-group strong{display:block;color:#0a2a43;font-size:13px;margin-bottom:8px}
.check-row{display:flex !important;align-items:center;gap:8px;margin:7px 0 !important;color:#475569 !important;font-weight:600 !important}
.check-row input{width:auto !important;min-width:16px;height:16px}
.check-row span{font-size:12px}
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
.access-list{max-width:340px;color:#475569;font-size:12px;line-height:1.6}
.access-pill{display:inline-block;padding:5px 9px;border-radius:999px;font-weight:800;font-size:12px}
.access-pill.full{background:#ecfdf3;color:#166534}
.access-pill.none{background:#fef2f2;color:#991b1b}
@media(max-width:1000px){.admin-users-page{margin-left:0}.layout{grid-template-columns:1fr}}
</style>
