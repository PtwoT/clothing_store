<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php'; // tạo ra $pdo

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

/* =============================
    LẤY DANH MỤC
============================= */
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")
                  ->fetchAll(PDO::FETCH_ASSOC);

/* =============================
    PHÂN TRANG + LỌC
============================= */
$limit  = 10;
$page   = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page   = max(1, $page);
$offset = ($page - 1) * $limit;

$categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;

/* =============================
    ĐẾM TỔNG SẢN PHẨM
============================= */
if ($categoryFilter > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :category_id");
    $stmt->execute([':category_id' => $categoryFilter]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
}
$totalProducts = (int)$stmt->fetchColumn();

$totalPages = max(1, ceil($totalProducts / $limit));
if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $limit;
}

/* =============================
    LẤY DANH SÁCH SẢN PHẨM
============================= */
$where = $categoryFilter > 0 ? "WHERE p.category_id = :category_id" : "";

$sql = "
    SELECT
        p.id,
        p.name,
        p.description,

        -- giá min trong các variant
        MIN(pv.price)         AS price,
        MIN(pv.price_reduced) AS price_reduced,

        -- tổng tồn kho
        SUM(pv.quantity)      AS stock,

        -- list size
        GROUP_CONCAT(DISTINCT siz.name ORDER BY siz.name SEPARATOR ', ') AS sizes,

        -- list màu
        GROUP_CONCAT(DISTINCT col.name ORDER BY col.name SEPARATOR ', ') AS colors,

        p.discount_percent,
        p.is_featured,
        p.created_at,
        c.name AS category_name,

        -- 1 ảnh đại diện
        MIN(pi.image_url)     AS image_url
    FROM products p
    LEFT JOIN categories      c   ON c.id = p.category_id
    LEFT JOIN product_variants pv ON pv.product_id = p.id
    LEFT JOIN sizes           siz ON siz.id = pv.size_id
    LEFT JOIN colors          col ON col.id = pv.color_id
    LEFT JOIN product_images  pi  ON pi.product_id = p.id
    $where
    GROUP BY 
        p.id, p.name, p.description,
        p.discount_percent, p.is_featured, p.created_at, c.name
    ORDER BY p.id DESC
    LIMIT :limit OFFSET :offset
";



$stmt = $pdo->prepare($sql);
if ($categoryFilter > 0) {
    $stmt->bindValue(':category_id', $categoryFilter, PDO::PARAM_INT);
}
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];


?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<title>Quản lý sản phẩm</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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

.content{margin-left:280px;padding:30px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:25px;}

.category-filter{display:flex;gap:10px;align-items:center;margin-bottom:15px;flex-wrap:wrap;}
.category-filter a{
    padding:8px 14px;border-radius:10px;background:#8E5DF5;color:#fff;
    font-weight:600;transition:.25s;
}
.category-filter a:hover{opacity:.9;transform:translateY(-1px);}
.category-filter a.active{background:#E91E63!important;}

.add-btn{
    padding:10px 14px;background:#E91E63;color:#fff;border:none;
    border-radius:8px;font-weight:600;cursor:pointer;transition:.3s;
}
.add-btn:hover{background:#ff4081;}

table{
    width:100%;border-collapse:collapse;background:#fff;border-radius:14px;
    overflow:hidden;margin-top:10px;
}
th{background:#8E5DF5;padding:14px;text-align:left;font-weight:600;color:#fff;}
td{padding:14px;border-bottom:1px solid #ddd;vertical-align:middle;}
tr:hover{background:#f9f9f9;}

img.product-img{
    width:55px;height:55px;border-radius:6px;object-fit:cover;max-width:100px;
}
.price-text{white-space:nowrap;}

.badge{
    display:inline-block;padding:4px 8px;border-radius:999px;
    font-size:12px;font-weight:600;
}
.badge-type-normal{background:#e0e0e0;color:#333;}
.badge-type-featured{background:#ffe0f0;color:#e91e63;}
.badge-instock{background:#e0f8e9;color:#2e7d32;}
.badge-outstock{background:#ffe0e0;color:#c62828;}

.btn-edit{
    background:#03A9F4;padding:6px 12px;border-radius:6px;color:#fff;
    font-size:14px;
}
.btn-edit:hover{background:#0288D1;}

.pagination{text-align:center;margin-top:18px;}
.pagination a{
    color:#fff;padding:8px 12px;border-radius:6px;background:#1a1a1a;
    margin:3px;transition:.3s;
}
.pagination a:hover{background:#8E5DF5;}
.pagination a.active{background:#E91E63;}
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
    <h1 class="page-title">Quản lý sản phẩm</h1>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
        <div class="category-filter">
            <a href="?category=0" class="<?= $categoryFilter==0?'active':'' ?>">Tất cả</a>
            <?php foreach($categories as $c): ?>
                <a href="?category=<?= $c['id'] ?>" class="<?= $categoryFilter==$c['id']?'active':'' ?>">
                    <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
        <a href="products_add.php" class="add-btn">+ Thêm sản phẩm</a>
    </div>

    <table>
        <tr>
            <th>Ảnh</th>
            <th>ID</th>
            <th>Tên</th>
            <th>Danh mục</th>
            <th>Size</th>
            <th>Màu sắc</th>
            <th>Giá gốc</th>
            <th>Giảm (%)</th>
            <th>Giá cuối</th>
            <th>Tồn kho</th>
            <th>Loại</th>
            <th>Ngày tạo</th>
            <th>Hành động</th>
        </tr>

        <?php foreach($products as $row): ?>
            <?php
    $price          = (float)($row['price'] ?? 0);
    $priceReduced   = (float)($row['price_reduced'] ?? 0);
    $discountPercent= (float)($row['discount_percent'] ?? 0);

    // tính giá cuối
    if ($priceReduced > 0) {
        $finalPrice = $priceReduced;
    } elseif ($price > 0 && $discountPercent > 0) {
        $finalPrice = $price * (1 - $discountPercent/100);
    } else {
        $finalPrice = $price;
    }

    $colors = $row['colors'] ?? '-';
    $stock  = (int)($row['stock'] ?? 0);

    // ảnh
    $imgSrc = '';
    if (!empty($row['image_url'])) {
        $imgSrc = '/clothing_store/uploads/' . ltrim($row['image_url'], '/');
    }

    $isFeatured = !empty($row['is_featured']);
    $typeLabel  = $isFeatured ? 'Sản phẩm nổi bật' : 'Sản phẩm thường';
    $typeClass  = $isFeatured ? 'badge-type-featured' : 'badge-type-normal';

    $stockLabel = $stock > 0 ? 'Còn hàng ('.$stock.')' : 'Hết hàng';
    $stockClass = $stock > 0 ? 'badge-instock' : 'badge-outstock';
?>

        <tr>
            <td>
                <?php if ($imgSrc): ?>
                    <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" class="product-img" alt="">
                <?php else: ?>
                    <span>Chưa có ảnh</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['category_name'] ?? 'Chưa gán', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['sizes'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['colors'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>

            <td class="price-text">
    <?php
        // dùng priceReduced thay discounted
        $style = ($discountPercent > 0 || $priceReduced > 0) 
            ? "text-decoration:line-through;color:#888;" 
            : "";
        echo "<span style='{$style}white-space:nowrap;'>" .
             number_format($price, 0, ',', '.') . " ₫</span>";
    ?>
</td>


            <td>
                <?= $discountPercent > 0 ? htmlspecialchars($discountPercent, ENT_QUOTES, 'UTF-8') . '%' : '-' ?>
            </td>

            <td>
                <?php
                    $showPrice = $finalPrice > 0 ? $finalPrice : $price;
                    echo "<span class='price-text' style='color:#E91E63;font-weight:600;white-space:nowrap;'>" .
                         number_format($showPrice, 0, ',', '.') . " ₫</span>";
                ?>
            </td>

            <td>
                <span class="badge <?= $stockClass; ?>">
                    <?= htmlspecialchars($stockLabel, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </td>

            <td>
                <span class="badge <?= $typeClass; ?>">
                    <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </td>

            <td><?= htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>

            <td>
                <a href="products_edit.php?id=<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn-edit">
                    Sửa
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="pagination">
        <?php
        if ($totalPages > 1) {
            if ($page > 2) {
                echo '<a href="?page=1&category=' . $categoryFilter . '">1</a>';
            }
            if ($page > 3) {
                echo '<span>...</span>';
            }
            for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++) {
                echo '<a href="?page=' . $i . '&category=' . $categoryFilter . '" class="' . ($i == $page ? "active" : "") . '">' . $i . '</a>';
            }
            if ($page < $totalPages - 2) {
                echo '<span>...</span>';
            }
            if ($page < $totalPages - 1) {
                echo '<a href="?page=' . $totalPages . '&category=' . $categoryFilter . '">' . $totalPages . '</a>';
            }
        }
        ?>
    </div>
</div>
</body>
</html>
