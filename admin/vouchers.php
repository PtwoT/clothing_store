<?php
session_name('admin_session');
session_start();

// --- Kết nối DB PDO ---
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

// ====== Lọc ====== //
// filter = all | active | expired | upcoming | out
$filter   = $_GET['filter'] ?? 'all';
$where_sql = "";
$params    = [];
$today     = date('Y-m-d');

if ($filter === 'active') {
    // đang diễn ra: trong khoảng ngày, còn lượt
    $where_sql = "WHERE begin <= :today_begin AND expired >= :today_end AND quantity > 0";
    $params[':today_begin'] = $today;
    $params[':today_end']   = $today;
} elseif ($filter === 'expired') {
    // đã hết hạn
    $where_sql = "WHERE expired < :today_end";
    $params[':today_end'] = $today;
} elseif ($filter === 'upcoming') {
    // chưa bắt đầu
    $where_sql = "WHERE begin > :today_begin";
    $params[':today_begin'] = $today;
} elseif ($filter === 'out') {
    // hết lượt
    $where_sql = "WHERE quantity <= 0";
}

// ====== Phân trang ====== //
$limit  = 10;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// --- Tổng số voucher ---
$total_sql = "SELECT COUNT(*) FROM vouchers $where_sql";
$stmt = $pdo->prepare($total_sql);
$stmt->execute($params);
$total_vouchers = (int)$stmt->fetchColumn();
$total_pages    = max(1, ceil($total_vouchers / $limit));

// --- Lấy danh sách voucher ---
$sql = "SELECT * FROM vouchers $where_sql ORDER BY id DESC LIMIT :offset, :limit";
$stmt = $pdo->prepare($sql);

// bind các tham số filter
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
// bind offset/limit
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);

$stmt->execute();
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hàm tính trạng thái voucher
function getVoucherStatus(array $v): string {
    $today   = date('Y-m-d');
    $begin   = $v['begin']   ?? null;
    $expired = $v['expired'] ?? null;
    $qty     = (int)($v['quantity'] ?? 0);

    if ($expired && $expired < $today) {
        return 'expired';
    }
    if ($qty <= 0) {
        return 'out';
    }
    if ($begin && $begin > $today) {
        return 'upcoming';
    }
    return 'active';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản lý vouchers</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box; font-family: 'Montserrat', sans-serif;}
body {display:flex; background:#f5f6fa; color:#111;}
.sidebar {width:260px;height:100vh;background:#fff;padding:30px 20px;position:fixed;border-right:1px solid #ddd;}
.sidebar h3 {color:#111;margin-bottom:25px;font-weight:700;font-size:20px;text-transform:uppercase;letter-spacing:2px;}
.sidebar a {display:flex;align-items:center;gap:10px;color:#111;padding:12px;border-radius:8px;text-decoration:none;font-size:15px;margin-bottom:5px;transition:0.25s;}
.sidebar a:hover {color:#8E5DF5;background:#f0e8ff;transform:translateX(5px);}
.content {margin-left:280px;padding:30px;width:100%;}
.page-header {display:flex;justify-content:space-between;align-items:center;}
.page-title {font-size:26px;font-weight:700;}
.add-btn {background:#E91E63;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:600;}
.add-btn:hover {background:#ff4081;}
.filter-buttons {margin-top:25px;}
.filter-buttons a {background:#8E5DF5;color:#fff;padding:10px 16px;border-radius:8px;margin-right:10px;text-decoration:none;font-weight:600;}
.filter-buttons a.active {background:#E91E63;}
table {width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;margin-top:20px;}
th {background:#8E5DF5;padding:14px;text-align:center;font-weight:600;color:#fff;}
td {padding:14px;text-align:center;border-bottom:1px solid #ddd;font-size:14px;}
tr:hover {background:#f9f9f9;}
.status-active   {color:#4CAF50;font-weight:600;}
.status-expired  {color:#ff4d4d;font-weight:600;}
.status-upcoming {color:#ff9800;font-weight:600;}
.status-out      {color:#9e9e9e;font-weight:600;}
.btn-edit {background:#03A9F4;padding:6px 12px;border-radius:6px;color:#fff;text-decoration:none;font-size:14px;}
.btn-edit:hover {background:#0288D1;}
.pagination {text-align:center;margin-top:18px;}
.pagination a {color:#fff;padding:8px 12px;border-radius:6px;background:#1a1a1a;margin:3px;text-decoration:none;}
.pagination a.active {background:#E91E63;}
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
    <div class="page-header">
        <h1 class="page-title">Quản lý vouchers</h1>
        <a href="vouchers_add.php" class="add-btn">+ Thêm voucher</a>
    </div>

    <div class="filter-buttons">
        <a href="?filter=all"      class="<?= ($filter=='all')      ? 'active':'' ?>">Tất cả</a>
        <a href="?filter=active"   class="<?= ($filter=='active')   ? 'active':'' ?>">Đang diễn ra</a>
        <a href="?filter=upcoming" class="<?= ($filter=='upcoming') ? 'active':'' ?>">Chưa bắt đầu</a>
        <a href="?filter=expired"  class="<?= ($filter=='expired')  ? 'active':'' ?>">Đã hết hạn</a>
        <a href="?filter=out"      class="<?= ($filter=='out')      ? 'active':'' ?>">Hết lượt</a>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Mã voucher</th>
            <th>Giá trị (%)</th>
            <th>Giảm tiền cố định</th>
            <th>Đơn tối thiểu</th>
            <th>Số lượng</th>
            <th>Bắt đầu</th>
            <th>Kết thúc</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
        </tr>

        <?php if ($vouchers): ?>
            <?php foreach ($vouchers as $v): ?>
                <?php
                    $status = getVoucherStatus($v);
                    $statusLabel = [
                        'active'   => '<span class="status-active">Đang diễn ra</span>',
                        'expired'  => '<span class="status-expired">Đã hết hạn</span>',
                        'upcoming' => '<span class="status-upcoming">Chưa bắt đầu</span>',
                        'out'      => '<span class="status-out">Hết lượt</span>',
                    ][$status];
                ?>
                <tr>
                    <td><?= (int)$v['id'] ?></td>
                    <td><?= htmlspecialchars($v['code'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= number_format((float)$v['value'], 0, '', '.') ?></td>
                    <td><?= $v['amount_reduced'] !== null ? number_format((float)$v['amount_reduced'], 0, '', '.') : '-' ?></td>
                    <td><?= number_format((float)$v['minimum_value'], 0, '', '.') ?></td>
                    <td><?= (int)$v['quantity'] ?></td>
                    <td><?= htmlspecialchars($v['begin']) ?></td>
                    <td><?= htmlspecialchars($v['expired']) ?></td>
                    <td><?= $statusLabel ?></td>
                    <td>
                        <a href="vouchers_edit.php?id=<?= (int)$v['id'] ?>" class="btn-edit">Sửa</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="10">Không có voucher nào.</td></tr>
        <?php endif; ?>
    </table>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?filter=<?= htmlspecialchars($filter) ?>&page=<?= $i ?>"
               class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>
</body>
</html>
