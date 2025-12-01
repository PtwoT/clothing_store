<?php
session_name('admin_session');
session_start();

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Lấy id user từ GET
if (!isset($_GET['id']) || !intval($_GET['id'])) {
    header("Location: users.php");
    exit;
}
$userId = (int)$_GET['id'];

// Lấy thông tin user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Không tìm thấy người dùng.");
    }

    // Lấy danh sách đơn hàng của user này
    $sqlOrders = "
        SELECT 
            id,
            total,
            status,
            payment_method,
            created_at,
            recipient_name,
            recipient_phone,
            recipient_address,
            recipient_email
        FROM orders
        WHERE user_id = :uid
        ORDER BY created_at DESC
    ";
    $stmt2 = $pdo->prepare($sqlOrders);
    $stmt2->execute([':uid' => $userId]);
    $orders = $stmt2->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Lỗi khi lấy dữ liệu: " . htmlspecialchars($e->getMessage()));
}

// Xử lý hiển thị sex / phone / active nếu có
$username   = $user['username'] ?? '';
$email      = $user['email'] ?? '';
$role       = $user['role'] ?? 'user';
$created_at = $user['created_at'] ?? '';

$phone = $user['phone'] ?? null;
$sex   = $user['sex']   ?? null;
$active = $user['active'] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Chi tiết người dùng</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#f5f6fa;color:#111;}
a{text-decoration:none;}

.sidebar{
    width:260px;height:100vh;background:#fff;padding:30px 20px;
    position:fixed;border-right:1px solid #ddd;
}
.sidebar h3{color:#111;margin-bottom:25px;font-weight:700;font-size:20px;text-transform:uppercase;letter-spacing:2px;}
.sidebar a{display:flex;align-items:center;gap:10px;color:#111;padding:12px;border-radius:8px;font-size:15px;margin-bottom:5px;transition:.25s;}
.sidebar a:hover{color:#8E5DF5;background:#f0e8ff;transform:translateX(5px);}
.sidebar a.logout{color:#ff4d4d;margin-top:40px;}

.content{
    margin-left:280px;padding:30px;width:calc(100% - 280px);min-height:100vh;
}
h1{font-size:24px;margin-bottom:10px;}
.sub{color:#777;margin-bottom:20px;}

.card{
    background:#fff;border-radius:14px;padding:20px;border:1px solid #ddd;
    margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.03);
}
.card h2{font-size:18px;margin-bottom:12px;}

.info-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:10px 20px;
    margin-top:10px;
}
.info-item span.label{
    font-size:13px;color:#777;display:block;margin-bottom:2px;
}
.info-item span.value{
    font-size:15px;font-weight:600;
}

.badge{
    display:inline-block;padding:4px 8px;border-radius:999px;
    font-size:12px;font-weight:600;
}
.badge-role-admin{background:#ffe0e0;color:#c62828;}
.badge-role-user{background:#e0f7fa;color:#006064;}
.badge-active{background:#e0f8e9;color:#2e7d32;}
.badge-locked{background:#ffe0e0;color:#c62828;}

table{
    width:100%;border-collapse:collapse;background:#fff;border-radius:14px;
    overflow:hidden;margin-top:10px;
}
th{background:#8E5DF5;padding:12px;text-align:left;font-weight:600;color:#fff;font-size:14px;}
td{padding:12px;border-bottom:1px solid #eee;font-size:14px;vertical-align:middle;}
tr:hover{background:#fafafa;}

.order-code{font-weight:600;}
.text-center{text-align:center;}
.back-link{margin-top:20px; }
.back-link a{color:#8E5DF5;font-weight:600;font-size: 16px;}
.back-link a:hover{text-decoration:underline;color:#E91E63;}
</style>
</head>
<body>
<div class="sidebar">
    <h3>YAMY ADMIN</h3>
    <a href="/clothing_store/admin/dashboard.php"><i class="fa fa-gauge"></i> Trang Quản Trị</a>
<a href="/clothing_store/admin/orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
<a href="/clothing_store/admin/users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
<a href="/clothing_store/admin/products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
<a href="/clothing_store/admin/news.php"><i class="fa fa-newspaper"></i> Quản lý tin tức</a>
<a href="/clothing_store/admin/vouchers.php"><i class="fa-solid fa-tags"></i> Quản lý vouchers</a>
<a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
    <h1>Chi tiết người dùng #<?= htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="sub">Xem thông tin tài khoản và lịch sử đơn hàng.</p>

    <!-- Thông tin tài khoản -->
    <div class="card">
        <h2>Thông tin tài khoản</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="label">Username</span>
                <span class="value"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Email</span>
                <span class="value"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Số điện thoại</span>
                <span class="value">
                    <?= $phone ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : '--' ?>
                </span>
            </div>
            <div class="info-item">
                <span class="label">Giới tính</span>
                <span class="value">
                    <?php
                    if ($sex === null || $sex === '') {
                        echo '--';
                    } elseif ((string)$sex === 'male') {
                        echo 'Nam';
                    } elseif ((string)$sex === 'female') {
                        echo 'Nữ';
                    } else {
                        echo htmlspecialchars((string)$sex, ENT_QUOTES, 'UTF-8');
                    }
                    ?>
                </span>
            </div>
            <div class="info-item">
                <span class="label">Vai trò</span>
                <span class="value">
                    <?php if ($role === 'admin'): ?>
                        <span class="badge badge-role-admin">Admin</span>
                    <?php else: ?>
                        <span class="badge badge-role-user">User</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="label">Trạng thái</span>
                <span class="value">
                    <?php if ($active === null): ?>
                        --
                    <?php elseif ((int)$active === 1): ?>
                        <span class="badge badge-active">Hoạt động</span>
                    <?php else: ?>
                        <span class="badge badge-locked">Khoá</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="label">Ngày tạo tài khoản</span>
                <span class="value"><?= htmlspecialchars($created_at, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </div>

    <!-- Danh sách đơn hàng -->
    <div class="card">
        <h2>Đơn hàng của người dùng</h2>

        <?php if (empty($orders)): ?>
            <p class="text-center" style="margin-top:10px;color:#777;">Người dùng này chưa có đơn hàng nào.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày đặt</th>
                    <th>Tên người nhận</th>
                    <th>Tổng tiền</th>
                    <th>Thanh toán</th>
                    <th>Trạng thái</th>
                </tr>
                <?php foreach ($orders as $o): ?>
                    <?php
                        $code = 'DH' . str_pad((string)$o['id'], 5, '0', STR_PAD_LEFT);
                        $total = isset($o['total']) ? (float)$o['total'] : 0;
                        $recipientName = $o['recipient_name'] ?? '--';
                    ?>
                    <tr>
                        <td class="order-code"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($o['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format($total, 0, ',', '.') ?> ₫</td>
                        <td><?= htmlspecialchars($o['payment_method'] ?? '--', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($o['status'] ?? '--', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="back-link">
        <a href="users.php">← Quay lại danh sách người dùng</a>
    </div>
</div>
</body>
</html>
