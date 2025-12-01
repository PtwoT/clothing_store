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
    $conn = $pdo; // từ db.php

    // Lấy id đơn hàng
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header("Location: orders.php");
        exit;
    }
    $orderId = (int)$_GET['id'];

    // ====== THÔNG TIN ĐƠN HÀNG ======
    // orders: id, user_id, total, payment_method, status, created_at,
    //         recipient_name, recipient_phone, recipient_address, recipient_email, note
    $sqlOrder = "SELECT * FROM orders WHERE id = :id LIMIT 1";
    $stmt = $conn->prepare($sqlOrder);
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo "<p style='color:red;text-align:center;margin-top:50px;'>Không tìm thấy đơn hàng.</p>";
        exit;
    }

    // Mã đơn dạng DH00001
    $orderCode = 'DH' . str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT);

    // ====== CHI TIẾT SẢN PHẨM ======
    /*
        order_details     : id, order_id, product_id, variant_id, quantity, price
        product_variants  : id, product_id, size_id, color_id, price, price_reduced, quantity
        sizes             : id, name
        colors            : id, name
        products          : id, name, ...
        product_images    : id, product_id, image_url

        Ở đây ta lấy 1 ảnh đại diện cho mỗi product (MIN(image_url)).
        image_url trong DB là TÊN FILE đã lưu trong thư mục /uploads
        (giống cách bạn dùng ở view/order_success).
    */
    $sqlItems = "
        SELECT 
            od.*,
            p.name       AS product_name,
            s.name       AS size_name,
            c.name       AS color_name,
            pi.image_url AS product_image
        FROM order_details od
        JOIN product_variants pv ON od.variant_id = pv.id
        JOIN products        p   ON pv.product_id = p.id
        LEFT JOIN sizes      s   ON pv.size_id = s.id
        LEFT JOIN colors     c   ON pv.color_id = c.id
        LEFT JOIN (
            SELECT product_id, MIN(image_url) AS image_url
            FROM product_images
            GROUP BY product_id
        ) pi ON pi.product_id = p.id
        WHERE od.order_id = :order_id
    ";

    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->execute([':order_id' => $orderId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Tổng tiền từ cột total (đã lưu trong orders)
    $orderTotal = (float)$order['total'];

    // ====== TRẠNG THÁI ĐƠN HÀNG (map sang tiếng Việt + badge) ======
    $statusRaw = $order['status'] ?? 'pending';
    $statusText = $statusRaw;
    $badgeClass = 'badge-pending';

    switch ($statusRaw) {
        case 'pending':
        case 'Chờ xác nhận':
            $statusText = 'Chờ xác nhận';
            $badgeClass = 'badge-pending';
            break;
        case 'processing':
        case 'Đang xử lý':
            $statusText = 'Đang xử lý';
            $badgeClass = 'badge-processing';
            break;
        case 'shipping':
        case 'Đơn hàng đang được giao':
            $statusText = 'Đơn hàng đang được giao';
            $badgeClass = 'badge-shipping';
            break;
        case 'completed':
        case 'Đã giao hàng':
            $statusText = 'Đã giao hàng';
            $badgeClass = 'badge-completed';
            break;
        case 'cancelled':
        case 'Hủy đơn hàng':
            $statusText = 'Hủy đơn hàng';
            $badgeClass = 'badge-cancel';
            break;
        default:
            $statusText = $statusRaw;
            $badgeClass = 'badge-pending';
            break;
    }

    // ====== PHƯƠNG THỨC THANH TOÁN ======
    $paymentRaw = $order['payment_method'] ?? 'cod';
    $paymentText = $paymentRaw;

    switch ($paymentRaw) {
        case 'cod':
            $paymentText = 'Thanh toán khi nhận hàng (COD)';
            break;
        case 'vnpay':
            $paymentText = 'Thanh toán qua VNPay';
            break;
        default:
            $paymentText = $paymentRaw;
            break;
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>Lỗi kết nối: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Chi tiết đơn hàng <?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?></title>
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
.page-title{font-size:26px;font-weight:700;margin-bottom:10px;}
.breadcrumb{font-size:13px;color:#777;margin-bottom:20px;}
.breadcrumb a{color:#8E5DF5;text-decoration:none;}

.card{
    background:var(--card-bg);
    border-radius:16px;
    padding:20px 22px;
    margin-bottom:20px;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}
.card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:16px;
}
.card-header h2{
    font-size:18px;
    font-weight:600;
}
.badge-status{
    padding:6px 12px;
    border-radius:999px;
    font-size:13px;
}
.badge-pending{background:#fff5d7;color:#b38300;}
.badge-processing{background:#e5f3ff;color:#005c99;}
.badge-shipping{background:#e3ffe7;color:#1b8b42;}
.badge-completed{background:#e0ffec;color:#1b8b42;}
.badge-cancel{background:#ffe6e6;color:#b3261e;}

.info-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:12px 20px;
}
.info-item-title{
    font-size:12px;
    text-transform:uppercase;
    color:#888;
    margin-bottom:4px;
}
.info-item-value{
    font-size:14px;
    font-weight:500;
}

.table-wrapper{
    margin-top:10px;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}
table{width:100%;border-collapse:collapse;background:var(--card-bg);}
th{background:#8E5DF5;padding:12px;text-align:left;font-weight:600;color:#fff;font-size:14px;}
td{padding:12px;border-bottom:1px solid var(--border-color);font-size:14px;}
tr:hover{background:var(--hover-color);}
.text-right{text-align:right;}
.total-row td{font-weight:600;font-size:15px;}

/* ô sản phẩm */
.product-cell{
    display:flex;
    align-items:center;
    gap:10px;
}
.product-img{
    width:50px;
    height:50px;
    border-radius:8px;
    object-fit:cover;
    border:1px solid var(--border-color);
}

.btn-back{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:#f0e8ff;
    padding:8px 14px;
    border-radius:999px;
    font-size:13px;
    text-decoration:none;
    color:#5a3ec8;
    margin-bottom:10px;
}
.btn-back i{font-size:12px;}
.back-link{margin-top:20px;}
.back-link a{
    color:#8E5DF5;
    font-weight:600;
    font-size:16px;
    text-decoration:none;      /* bỏ gạch chân mặc định */
}

.back-link a:hover{
    text-decoration:underline; /* chỉ gạch chân khi hover */
    color:#E91E63;
}

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
    <a href="orders.php" class="btn-back"><i class="fa fa-arrow-left"></i> Quay lại danh sách</a>
    <h1 class="page-title">Chi tiết đơn hàng</h1>
    <div class="breadcrumb">
        <a href="orders.php">Quản lý đơn hàng</a> &raquo;
        Đơn: <strong><?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?></strong>
    </div>

    <!-- THÔNG TIN ĐƠN HÀNG -->
    <div class="card">
        <div class="card-header">
            <h2>Thông tin đơn hàng</h2>
            <span class="badge-status <?= $badgeClass ?>">
                <?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <div class="info-grid">
            <div>
                <div class="info-item-title">Mã đơn hàng</div>
                <div class="info-item-value"><?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
                <div class="info-item-title">Tên người nhận</div>
                <div class="info-item-value">
                    <?= htmlspecialchars($order['recipient_name'] ?? '--', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Số điện thoại</div>
                <div class="info-item-value">
                    <?= htmlspecialchars($order['recipient_phone'] ?? '--', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Địa chỉ nhận hàng</div>
                <div class="info-item-value">
                    <?= nl2br(htmlspecialchars($order['recipient_address'] ?? '--', ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Phương thức thanh toán</div>
                <div class="info-item-value">
                    <?= htmlspecialchars($paymentText, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Tổng tiền</div>
                <div class="info-item-value">
                    <?= number_format($orderTotal, 0, ',', '.') ?> đ
                </div>
            </div>
            <div>
                <div class="info-item-title">Ghi chú</div>
                <div class="info-item-value">
                    <?= nl2br(htmlspecialchars($order['note'] ?? 'Không có', ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Ngày đặt</div>
                <div class="info-item-value">
                    <?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- DANH SÁCH SẢN PHẨM -->
    <div class="card">
        <div class="card-header">
            <h2>Sản phẩm trong đơn</h2>
        </div>

        <?php if (!empty($items)): ?>
            <div class="table-wrapper">
                <table>
                    <tr>
                        <th>Stt</th>
                        <th>Sản phẩm</th>
                        <th>Size</th>
                        <th>Màu</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th class="text-right">Thành tiền</th>
                    </tr>
                    <?php
                    $i = 1;
                    $calcTotal = 0;
                    foreach ($items as $item):
                        $lineTotal = $item['quantity'] * $item['price'];
                        $calcTotal += $lineTotal;

                        // ẢNH: lấy tên file từ product_image, ghép với thư mục uploads (giống view/order_success)
                        $imgFile = !empty($item['product_image'])
                            ? $item['product_image']
                            : 'no-image.png';   // ảnh mặc định nếu không có

                        // order_detail.php nằm trong /admin nên dùng ../uploads/
                        $imgSrc = '../uploads/' . htmlspecialchars($imgFile, ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td>
                                <div class="product-cell">
                                    <img src="<?= $imgSrc ?>" alt="Ảnh" class="product-img">
                                    <span><?= htmlspecialchars($item['product_name'] ?? 'Sản phẩm #' . $item['product_id'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($item['size_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['color_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$item['quantity']; ?></td>
                            <td><?= number_format($item['price'], 0, ',', '.'); ?> đ</td>
                            <td class="text-right"><?= number_format($lineTotal, 0, ',', '.'); ?> đ</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <!-- 6 cột (Stt..Đơn giá) + 1 cột Thành tiền -->
                        <td colspan="6">Tổng tiền</td>
                        <td class="text-right"><?= number_format($calcTotal, 0, ',', '.'); ?> đ</td>
                    </tr>
                </table>
            </div>
        <?php else: ?>
            <p style="margin-top:10px;color:#777;">Đơn hàng không có sản phẩm nào.</p>
        <?php endif; ?>
    </div>

    <div class="back-link">
        <a href="orders.php">← Quay lại danh sách đơn hàng</a>
    </div>
</div>
</body>
</html>
