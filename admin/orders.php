<?php
ini_set('session.cookie_path', '/');
session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ====== CHECK ADMIN ======
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../view/login.php");
    exit;
}

try {
    require_once __DIR__ . '/../config/db.php';
    $conn = $pdo; // $pdo là từ db.php

    // ====== PHÂN TRANG ======
    $ordersPerPage = 10;
    $page = isset($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;

    $countSql = "SELECT COUNT(*) FROM orders";
    $totalOrders = (int)$conn->query($countSql)->fetchColumn();
    $totalPages = $totalOrders > 0 ? ceil($totalOrders / $ordersPerPage) : 1;
    $offset = ($page - 1) * $ordersPerPage;

    // ====== CHỈ LẤY TỪ BẢNG ORDERS ======
    $sql = "
        SELECT *
        FROM orders
        ORDER BY created_at DESC
        LIMIT :offset, :limit
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit',  $ordersPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    echo "<p style='color: red;'>Lỗi kết nối: " . htmlspecialchars($e->getMessage()) . "</p>";
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản lý đơn hàng</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
:root{
    --bg-main:#f7f5ff;
    --bg-sidebar:#ffffff;
    --card-bg:#ffffff;
    --text-color:#222;
    --border-color:#e0d7ff;
    --hover-color:#f5f0ff;
}
body{display:flex;background:var(--bg-main);color:var(--text-color);}

.sidebar{width:260px;height:100vh;background:var(--bg-sidebar);padding:30px 20px;position:fixed;border-right:1px solid var(--border-color);}
.sidebar h3{color:var(--text-color);margin-bottom:25px;font-weight:700;font-size:20px;text-transform:uppercase;letter-spacing:2px;}
.sidebar a{display:flex;align-items:center;gap:10px;color:var(--text-color);opacity:.8;padding:12px;border-radius:8px;text-decoration:none;font-size:15px;margin-bottom:5px;transition:.25s;}
.sidebar a:hover{color:#8E5DF5;background:#f0e8ff;transform:translateX(5px);}

.content{margin-left:280px;padding:30px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:25px;}
.btn-edit {background:#03A9F4;padding:6px 12px;border-radius:6px;color:#fff;text-decoration:none;font-size:14px;}
.btn-edit:hover {background:#0288D1;}
table{width:100%;border-collapse:collapse;background:var(--card-bg);border-radius:14px;overflow:hidden;margin-top:10px;box-shadow:0 4px 10px rgba(0,0,0,0.05);}
th{background:#8E5DF5;padding:14px;text-align:left;font-weight:600;color:#fff;}
td{padding:14px;border-bottom:1px solid var(--border-color);}
tr:hover{background:var(--hover-color);}

.pagination{text-align:center;margin-top:25px;}
.pagination a{color:var(--text-color);padding:8px 12px;border-radius:6px;background:var(--hover-color);margin:3px;text-decoration:none;transition:.3s;}
.pagination a.active{background:#E91E63;color:#fff;}
.pagination a:hover{opacity:.8;}
</style>
</head>

<body>
<div class="sidebar">
    <h3>YaMy Admin</h3>
    <a href="/clothing_store/admin/dashboard.php"><i class="fa fa-gauge"></i> Trang Quản Trị</a>
<a href="/clothing_store/admin/orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
<a href="/clothing_store/admin/users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
<a href="/clothing_store/admin/products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
<a href="/clothing_store/admin/news.php"><i class="fa fa-newspaper"></i> Quản lý tin tức</a>
<a href="/clothing_store/admin/vouchers.php"><i class="fa-solid fa-tags"></i> Quản lý vouchers</a>
<a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
  <h1 class="page-title">Quản lý đơn hàng</h1>

<?php if (!empty($orders)): ?>
  <table>
    <tr>
      <th>ID</th>
      <th>Ngày đặt</th>
      <th>Mã đơn hàng</th>
      <th>Tên khách hàng</th>
      <th>Trạng thái</th>
      <th>Hành động</th>
    </tr>
    <?php 
    $allStatuses = [
        "Chờ xác nhận",
        "Đang xử lý",
        "Đơn hàng đang được giao",
        "Đã giao hàng",
        "Hủy đơn hàng"
    ];

    foreach ($orders as $order): 
        // Trạng thái đơn
        $status = $order['status'] ?? 'Chờ xác nhận';
        $isFinal = in_array($status, ['Đã giao hàng', 'Hủy đơn hàng'], true);

        // Mã đơn tự tạo từ id: DH00001, ...
        $orderCode = 'DH' . str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT);

        // Tên người nhận từ cột recipient_name
        $receiverName = $order['recipient_name'] ?? '--';
    ?>
    <tr data-id="<?= htmlspecialchars((string)$order['id'], ENT_QUOTES, 'UTF-8') ?>">
      <td><?= htmlspecialchars((string)$order['id'], ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= htmlspecialchars($receiverName, ENT_QUOTES, 'UTF-8') ?></td>

      <!-- Cột trạng thái (dropdown) -->
      <td>
        <select class="order-status" data-id="<?= htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $isFinal ? 'disabled' : '' ?>>
            <?php foreach ($allStatuses as $s): ?>
                <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $status === $s ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
      </td>

      <!-- Cột hành động -->
      <td>
        <a href="order_detail.php?id=<?= $order['id']; ?>" 
          class="btn-edit">
            Chi tiết
        </a>
      </td>

    </tr>
    <?php endforeach; ?>
  </table>
<?php else: ?>
  <p style="text-align:center;color:#888;margin-top:50px;">Không có đơn hàng nào.</p>
<?php endif; ?>

</div>

<script>
const allStatuses = [
  "Chờ xác nhận",
  "Đang xử lý",
  "Đơn hàng đang được giao",
  "Đã giao hàng",
  "Hủy đơn hàng"
];

document.querySelectorAll('.order-status').forEach(select => {
  select.addEventListener('change', function () {
    const id = this.dataset.id;
    const newStatus = this.value;
    const selectEl = this;

    fetch('/clothing_store/admin/update_order_status.php', {
      method: "POST",
      headers: {"Content-Type": "application/x-www-form-urlencoded"},
      body: `id=${encodeURIComponent(id)}&status=${encodeURIComponent(newStatus)}`
    })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        alert(data.message || "Lỗi cập nhật trạng thái!");
        return;
      }

      // Highlight hiệu ứng
      selectEl.style.backgroundColor = "#d1ffd1";
      setTimeout(() => { selectEl.style.backgroundColor = ""; }, 800);

      // Khoá nếu hoàn tất
      if (["Đã giao hàng", "Hủy đơn hàng"].includes(newStatus)) {
        selectEl.disabled = true;

        const actionTd = selectEl.parentElement.nextElementSibling;
        if (actionTd) {
          actionTd.innerHTML = '<span style="color:#888;font-style:italic;">Hoàn tất</span>';
        }
      }
    })
    .catch(err => {
      console.error(err);
      alert("Không thể kết nối server!");
    });
  });
});
</script>

</body>
</html>
