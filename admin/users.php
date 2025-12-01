<?php
session_name('admin_session');
session_start();

// --- Kết nối DB PDO ---
require_once __DIR__ . '/../config/db.php'; // $pdo từ đây

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// --- Lấy tên người dùng hiện tại ---
$username = $_SESSION['username'] ?? 'Admin';
$username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

// ====== Lọc theo vai trò ====== //
$filter = $_GET['filter'] ?? 'all';
$where_sql = "";
$params = [];

if ($filter == 'Admin') {
    $where_sql = "WHERE role = :role";
    $params[':role'] = 'Admin';   // ✔ giống hệt trong DB
} elseif ($filter == 'User') {
    $where_sql = "WHERE role = :role";
    $params[':role'] = 'User';    // ✔ giống hệt trong DB
}


// ====== Phân trang ====== //
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// --- Tổng số người dùng ---
$total_sql = "SELECT COUNT(*) as total FROM users $where_sql";
$stmt = $pdo->prepare($total_sql);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// --- Lấy danh sách người dùng --- //
$sql = "SELECT * FROM users $where_sql ORDER BY id DESC LIMIT :offset, :limit";
$stmt = $pdo->prepare($sql);

// Với LIMIT và OFFSET, PDO cần bindValue kiểu integer
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR); // ✔ role là string
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản lý người dùng</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* CSS giữ nguyên từ code cũ */
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
.stats {display:flex;gap:20px;margin-top:20px;}
.stat-box {background:#fff;border-radius:16px;padding:20px;height:110px;box-shadow:0 2px 6px rgba(0,0,0,0.08);}
.stat-box h4 {font-size:14px;font-weight:600;color:#555;}
.stat-box p {font-size:26px;font-weight:700;color:#8E5DF5;margin-top:6px;}
.filter-buttons {margin-top:25px;}
.filter-buttons a {background:#8E5DF5;color:#fff;padding:10px 16px;border-radius:8px;margin-right:10px;text-decoration:none;font-weight:600;}
.filter-buttons a.active {background:#E91E63;}
table {width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;margin-top:20px;}
th {background:#8E5DF5;padding:14px;text-align:center;font-weight:600;color:#fff;}
td {padding:14px;text-align:center;border-bottom:1px solid #ddd;}
tr:hover {background:#f9f9f9;}
.status-active {color:#4CAF50;font-weight:600;}
.status-locked {color:#ff4d4d;font-weight:600;}
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
        <h1 class="page-title">Quản lý người dùng</h1>
        <a href="user_add.php" class="add-btn">+ Thêm người dùng</a>
    </div>

    <div class="filter-buttons">
        <a href="?filter=all" class="<?= ($filter=='all') ? 'active':'' ?>">Tất cả</a>
        <a href="?filter=User" class="<?= ($filter=='User') ? 'active':'' ?>">User</a>
        <a href="?filter=Admin" class="<?= ($filter=='Admin') ? 'active':'' ?>">Admin</a>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Tên đăng nhập</th>
            <th>Email</th>
            <th>Số điện thoại</th>
            <th>Giới tính</th>
            <th>Vai trò</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
        </tr>
        <?php foreach ($users as $u): ?>
<tr>
    <td><?= $u['id'] ?></td>

    <td><?= htmlspecialchars($u['username'] ?? '--', ENT_QUOTES, 'UTF-8') ?></td>

    <td><?= htmlspecialchars($u['email'] ?? '--', ENT_QUOTES, 'UTF-8') ?></td>

    <td><?= htmlspecialchars($u['phone'] ?? '--', ENT_QUOTES, 'UTF-8') ?></td>

    <td>
        <?php
        $sex = $u['sex'] ?? null;
        echo ($sex == 'male' ? 'Nam' : ($sex == 'female' ? 'Nữ' : '--'));
        ?>
    </td>

    <td><?= htmlspecialchars($u['role'] ?? 'user', ENT_QUOTES, 'UTF-8') ?></td>

    <td>
        <?php
        $active = $u['active'] ?? 1; // nếu chưa có active, mặc định là hoạt động
        echo ($active == 1
            ? '<span class="status-active">Hoạt động</span>'
            : '<span class="status-locked">Khoá</span>');
        ?>
    </td>

    <td><a href="user_detail.php?id=<?= $u['id'] ?>" class="btn-edit">Chi tiết</a></td>
</tr>
<?php endforeach; ?>

    </table>

    <div class="pagination">
        <?php for($i=1; $i<=$total_pages; $i++): ?>
            <a href="?filter=<?= $filter ?>&page=<?= $i ?>" class="<?= ($i==$page)?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>
</body>
</html>
