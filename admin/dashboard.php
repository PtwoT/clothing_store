<?php
session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin quyền
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// DB
require_once __DIR__ . '/../config/db.php';
$conn = $pdo;

// Lấy tên người dùng từ session
$username = $_SESSION['user']['hoten']
        ?? $_SESSION['user']['name']
        ?? $_SESSION['user']['username']
        ?? 'Admin';
$username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

try {

    // Tổng doanh thu
    $revenueQuery = $conn->query("
        SELECT SUM(total) AS total_revenue
        FROM orders
        WHERE status = 'Đã giao hàng'
    ");
    $revenue = $revenueQuery->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

    // Tổng đơn
    $orderQuery = $conn->query("
        SELECT COUNT(*) AS total_orders
        FROM orders
        WHERE status = 'Đã giao hàng'
    ");
    $totalOrders = $orderQuery->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;

    // Tổng khách hàng
    $userQuery = $conn->query("SELECT COUNT(*) AS total_users FROM users WHERE role = 'user'");
    $totalUsers = $userQuery->fetch(PDO::FETCH_ASSOC)['total_users'] ?? 0;

    // Tổng sản phẩm
    $productQuery = $conn->query("SELECT COUNT(*) AS total_products FROM products");
    $totalProducts = $productQuery->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;

    // Biểu đồ doanh thu theo tháng
    $monthlyQuery = $conn->query("
        SELECT DATE_FORMAT(created_at, '%m') AS month, SUM(total) AS total
        FROM orders
        WHERE status = 'Đã giao hàng'
        GROUP BY month
        ORDER BY month
    ");

    $months = [];
    $totals = [];
    while ($row = $monthlyQuery->fetch(PDO::FETCH_ASSOC)) {
        $months[] = $row['month'];
        $totals[] = $row['total'];
    }

    // Biểu đồ sản phẩm theo danh mục
    $categoryQuery = $conn->query("
        SELECT categories.name, COUNT(products.id) AS total
        FROM categories
        LEFT JOIN products ON categories.id = products.category_id
        GROUP BY categories.id, categories.name
    ");

    $categoryNames = [];
    $productCounts = [];
    while ($row = $categoryQuery->fetch(PDO::FETCH_ASSOC)) {
        $categoryNames[] = $row['name'];
        $productCounts[] = $row['total'];
    }

} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>YAMY Admin - Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#f5f5f5;color:#333;}
.sidebar{
    width:260px;background:#fff;height:100vh;padding:30px 20px;position:fixed;
    border-right:1px solid #ddd;
}
.sidebar h3{font-size:22px;font-weight:700;margin-bottom:25px;}
.sidebar a{
    display:flex;align-items:center;gap:10px;padding:12px;
    color:#333;text-decoration:none;border-radius:8px;margin-bottom:8px;
    transition:.25s;font-weight:500;font-size:15px;
}
.sidebar a:hover{background:#f2e8ff;color:#8E5DF5;transform:translateX(4px);}
.sidebar .logout{color:#e53935;margin-top:20px;}

.content{
    flex:1; padding:35px 40px; margin-left:260px;
}

.stats{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;margin-top:25px;
}
.card{
    background:#fff;padding:22px;border-radius:14px;border:1px solid #ddd;
    text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.04);
}
.card h4{font-size:15px;color:#666;margin-bottom:10px;}
.card p{font-size:28px;font-weight:700;margin-top:5px;}

.chart-box{
    background:#fff;margin-top:35px;padding:25px;border-radius:14px;border:1px solid #ddd;
}
.switch button{
    padding:8px 14px;border:none;border-radius:6px;margin-right:10px;
    font-weight:600;color:#fff;cursor:pointer;
}
</style>
</head>

<body>

<div class="sidebar">
    <h3>YAMY ADMIN</h3>
    <a href="dashboard.php"><i class="fa fa-gauge"></i> Trang Quản Trị</a>
    <a href="orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
    <a href="users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
    <a href="products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
    <a href="news.php"><i class="fa fa-newspaper"></i> Quản lý tin tức</a>
    <a href="vouchers.php"><i class="fa-solid fa-tags"></i> Quản lý vouchers</a>
    <a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
    <h1>Xin chào, <?= $username ?> 👋</h1>
    <p>Chúc bạn một ngày làm việc hiệu quả!</p>

    <div class="stats">
        <div class="card"><h4>Doanh thu</h4><p style="color:#4CAF50"><?= number_format($revenue,0,',','.') ?> ₫</p></div>
        <div class="card"><h4>Đơn hàng</h4><p style="color:#03A9F4"><?= $totalOrders ?></p></div>
        <div class="card"><h4>Khách hàng</h4><p style="color:#FF9800"><?= $totalUsers ?></p></div>
        <div class="card"><h4>Sản phẩm</h4><p style="color:#E91E63"><?= $totalProducts ?></p></div>
    </div>

    <div class="chart-box">
        <h2>Biểu đồ thống kê</h2>
        <div class="switch">
            <button id="btnRevenue" style="background:#8E5DF5">Doanh thu</button>
            <button id="btnProduct" style="background:#E91E63">Danh mục sản phẩm</button>
        </div>

        <canvas id="revenueChart" style="margin-top:20px;"></canvas>
        <canvas id="productChart" style="margin-top:20px;display:none;"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const revenueChart = new Chart(document.getElementById('revenueChart'), {
    type:'line',
    data:{
        labels:<?= json_encode($months) ?>,
        datasets:[{
            label:'Doanh thu (VNĐ)',
            data:<?= json_encode($totals) ?>,
            borderColor:'#8E5DF5',
            borderWidth:3,
            tension:0.3
        }]
    }
});

const productChart = new Chart(document.getElementById('productChart'), {
    type:'bar',
    data:{
        labels:<?= json_encode($categoryNames) ?>,
        datasets:[{
            label:'Số lượng sản phẩm',
            data:<?= json_encode($productCounts) ?>,
            backgroundColor:'#E91E63'
        }]
    }
});

document.getElementById('btnRevenue').onclick = () => {
    document.getElementById('revenueChart').style.display = "block";
    document.getElementById('productChart').style.display = "none";
};

document.getElementById('btnProduct').onclick = () => {
    document.getElementById('revenueChart').style.display = "none";
    document.getElementById('productChart').style.display = "block";
};
</script>

</body>
</html>
