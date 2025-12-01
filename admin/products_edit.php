<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Lấy id sản phẩm
if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}
$productId = (int)$_GET['id'];

/* =============================
   LẤY SẢN PHẨM
============================= */
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    die("Không tìm thấy sản phẩm.");
}

/* =============================
   LẤY ẢNH SẢN PHẨM
============================= */
$stmtImg = $pdo->prepare("
    SELECT id, image_url
    FROM product_images
    WHERE product_id = :pid
    ORDER BY id ASC
    LIMIT 1
");
$stmtImg->execute([':pid' => $productId]);
$productImage = $stmtImg->fetch(PDO::FETCH_ASSOC); // có thể là false

/* =============================
   LẤY DANH MỤC / SIZE / COLOR
============================= */
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")
                  ->fetchAll(PDO::FETCH_ASSOC);

$sizes = $pdo->query("SELECT id, name FROM sizes ORDER BY id ASC")
             ->fetchAll(PDO::FETCH_ASSOC);


$colors = $pdo->query("SELECT id, name FROM colors ORDER BY name ASC")
              ->fetchAll(PDO::FETCH_ASSOC);

/* =============================
   LẤY BIẾN THỂ (JOIN SIZE & COLOR)
============================= */
$variantsStmt = $pdo->prepare("
    SELECT v.*, s.name AS size_name, c.name AS color_name
    FROM product_variants v
    LEFT JOIN sizes  s ON s.id = v.size_id
    LEFT JOIN colors c ON c.id = v.color_id
    WHERE v.product_id = :pid
    ORDER BY v.id ASC
");
$variantsStmt->execute([':pid' => $productId]);
$variants = $variantsStmt->fetchAll(PDO::FETCH_ASSOC);

/* =============================
   XỬ LÝ FORM CẬP NHẬT SẢN PHẨM
============================= */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {

    $name             = trim($_POST['name'] ?? '');
    $category_id      = (int)($_POST['category_id'] ?? 0);
    // dropdown -> giá trị số
    $is_featured      = isset($_POST['is_featured']) ? (int)$_POST['is_featured'] : 0;
    $status           = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $description      = trim($_POST['description'] ?? '');
    $discount_percent = isset($_POST['discount_percent']) ? (float)$_POST['discount_percent'] : 0;

    if ($name === '') {
        $error = "Tên không được để trống.";
    } elseif ($category_id <= 0) {
        $error = "Vui lòng chọn danh mục.";
    }

    // Ảnh hiện tại
    $currentImage   = $productImage['image_url'] ?? null;
    $currentImageId = $productImage['id'] ?? null;
    $newImage       = $currentImage;

    // Upload ảnh nếu có chọn file
    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // THƯ MỤC uploads: /clothing_store/uploads
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $basename = basename($_FILES['image']['name']);
            $ext      = pathinfo($basename, PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($basename, PATHINFO_FILENAME));
            $filename = uniqid('prod_', true) . '_' . $safeName . '.' . $ext;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $newImage = $filename;
            } else {
                $error = "Không thể tải ảnh lên.";
            }
        } else {
            $error = "Lỗi upload ảnh (mã lỗi: " . (int)$_FILES['image']['error'] . ").";
        }
    }

    if (!$error) {
        // Cập nhật bảng products
        $sql = "UPDATE products SET 
                    name             = :name,
                    description      = :description,
                    category_id      = :category_id,
                    is_featured      = :is_featured,
                    discount_percent = :discount_percent,
                    status           = :status
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'             => $name,
            ':description'      => $description,
            ':category_id'      => $category_id,
            ':is_featured'      => $is_featured,
            ':discount_percent' => $discount_percent,
            ':status'           => $status,
            ':id'               => $productId
        ]);

        // Cập nhật bảng product_images nếu có thay đổi ảnh
        if ($newImage && $newImage !== $currentImage) {
            if ($currentImageId) {
                $stmtImgUpdate = $pdo->prepare("
                    UPDATE product_images
                    SET image_url = :img
                    WHERE id = :id
                ");
                $stmtImgUpdate->execute([
                    ':img' => $newImage,
                    ':id'  => $currentImageId
                ]);
            } else {
                $stmtImgInsert = $pdo->prepare("
                    INSERT INTO product_images (product_id, image_url)
                    VALUES (:pid, :img)
                ");
                $stmtImgInsert->execute([
                    ':pid' => $productId,
                    ':img' => $newImage
                ]);
            }
        }

        header("Location: products_edit.php?id={$productId}&success=product");
        exit;
    }
}

/* =============================
   XỬ LÝ FORM CẬP NHẬT / XÓA BIẾN THỂ
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_variants'])) {

    // Xóa 1 biến thể
    if (isset($_POST['delete_variant'])) {
        $delId = (int)$_POST['delete_variant'];

        $stmtDel = $pdo->prepare("
            DELETE FROM product_variants
            WHERE id = :id AND product_id = :pid
        ");
        $stmtDel->execute([
            ':id'  => $delId,
            ':pid' => $productId
        ]);

        header("Location: products_edit.php?id={$productId}&success=variant_deleted");
        exit;
    }

    // Cập nhật các biến thể
    if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
        foreach ($_POST['variants'] as $variant_id => $v) {
            $size_id       = isset($v['size_id'])  ? (int)$v['size_id']  : null;
            $color_id      = isset($v['color_id']) ? (int)$v['color_id'] : null;
            $price         = isset($v['price']) ? (float)$v['price'] : 0;
            $price_reduced = isset($v['price_reduced']) ? (float)$v['price_reduced'] : 0;
            $quantity      = isset($v['quantity']) ? (int)$v['quantity'] : 0;

            $stmt = $pdo->prepare("
                UPDATE product_variants 
                SET size_id       = :size_id,
                    color_id      = :color_id,
                    price         = :price,
                    price_reduced = :price_reduced,
                    quantity      = :quantity
                WHERE id = :id AND product_id = :pid
            ");
            $stmt->execute([
                ':size_id'       => $size_id,
                ':color_id'      => $color_id,
                ':price'         => $price,
                ':price_reduced' => $price_reduced,
                ':quantity'      => $quantity,
                ':id'            => $variant_id,
                ':pid'           => $productId
            ]);
        }
    }

    header("Location: products_edit.php?id={$productId}&success=variants");
    exit;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Sửa sản phẩm</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#f5f6fa;color:#111;}
.sidebar{
  width:260px;height:100vh;background:#fff;padding:30px 20px;
  position:fixed;border-right:1px solid #ddd;
}
.sidebar h3{
  color:#111;margin-bottom:25px;font-weight:700;font-size:20px;
  text-transform:uppercase;letter-spacing:2px;
}
.sidebar a{
  display:flex;align-items:center;gap:10px;color:#111;
  padding:12px;border-radius:8px;text-decoration:none;
  font-size:15px;margin-bottom:5px;transition:.25s;
}
.sidebar a:hover{
  color:#8E5DF5;background:#f0e8ff;transform:translateX(5px);
}
.sidebar a.logout{color:#ff4d4d;margin-top:40px;}
.content{
  margin-left:280px;padding:40px;width:100%;
}
.page-title{
  font-size:26px;font-weight:700;margin-bottom:25px;text-align:center;
}
.form-card{
  background:#fff;border-radius:16px;padding:30px;max-width:900px;
  margin:0 auto;box-shadow:0 4px 14px rgba(0,0,0,0.1);
}
form label{
  font-weight:600;margin-top:18px;display:block;
}
form input,form textarea,form select{
  width:100%;padding:12px;border-radius:8px;border:1px solid #ccc;
  margin-top:6px;font-size:14px;
}
form textarea{resize:vertical;}
button{
  width:100%;padding:14px;margin-top:25px;background:#8E5DF5;
  border:none;border-radius:10px;color:#fff;font-weight:700;
  font-size:16px;cursor:pointer;transition:.3s;
}
button:hover{background:#E91E63;}
img.product-preview{
  width:120px;border-radius:10px;margin-top:10px;display:block;
}
.note{margin-top:10px;color:#16a34a;font-weight:600;}
.error{margin-top:10px;color:#ef4444;font-weight:600;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{
  border:1px solid #ddd;padding:10px;text-align:left;vertical-align:middle;
}
th{background:#8E5DF5;color:#fff;}
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0;}
.back-link{text-align:center;margin-top:20px;}
.back-link a{
  color:#8E5DF5;text-decoration:none;font-weight:600;
}
.back-link a:hover{text-decoration:underline;}

/* Nút nhỏ trong bảng */
.btn-inline{
  width:auto !important;
  margin-top:0;
  padding:6px 12px;
  font-size:14px;
}

/* Nút xóa biến thể */
.btn-delete-variant{
  background:#ff4d4d;
}
.btn-delete-variant:hover{
  background:#cc0000;
}

/* Giao diện bảng biến thể giống trang add */
.variants-wrapper{
  margin-top:40px;
  background:#fff;
  border-radius:16px;
  padding:25px 30px 28px;
  box-shadow:0 2px 12px rgba(0,0,0,.08);
}
.variants-title{
  font-size:18px;
  font-weight:700;
  color:#8E5DF5;
  margin-bottom:6px;
}
.variants-sub{
  font-size:13px;
  color:#777;
  margin-bottom:12px;
}

#variants-table{
  width:100%;
  border-collapse:collapse;
  table-layout:fixed;
  margin-top:5px;
}
#variants-table th,
#variants-table td{
  border:1px solid #eee;
  padding:12px;
  text-align:center;
  vertical-align:middle;
  font-size:14px;
}
#variants-table th{
  background:#f4ecff;
  color:#333;
  font-weight:600;
}

/* chiều rộng cột */
#variants-table th:nth-child(1), #variants-table td:nth-child(1){width:8%;}
#variants-table th:nth-child(2), #variants-table td:nth-child(2){width:16%;}
#variants-table th:nth-child(3), #variants-table td:nth-child(3){width:16%;}
#variants-table th:nth-child(4), #variants-table td:nth-child(4){width:18%;}
#variants-table th:nth-child(5), #variants-table td:nth-child(5){width:18%;}
#variants-table th:nth-child(6), #variants-table td:nth-child(6){width:14%;}
#variants-table th:nth-child(7), #variants-table td:nth-child(7){width:10%;}

#variants-table select,
#variants-table input[type="number"]{
  width:100%;
  max-width:150px;
  padding:10px;
  border-radius:10px;
  border:1px solid #ccc;
  font-size:14px;
}
#variants-table td.variant-action{
  text-align:center;
}
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
  <h1 class="page-title">Sửa sản phẩm</h1>

  <div class="form-card">
    <?php
    if (isset($_GET['success']) && $_GET['success'] === 'product')
        echo '<div class="note">Cập nhật sản phẩm thành công!</div>';
    if (isset($_GET['success']) && $_GET['success'] === 'variants')
        echo '<div class="note">Cập nhật biến thể thành công!</div>';
    if (isset($_GET['success']) && $_GET['success'] === 'variant_deleted')
        echo '<div class="note">Đã xóa biến thể thành công!</div>';
    if ($error)
        echo '<div class="error">'.htmlspecialchars($error, ENT_QUOTES, 'UTF-8').'</div>';
    ?>

    <!-- FORM SẢN PHẨM -->
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="update_product" value="1">

      <label>Ảnh sản phẩm:</label>
      <input type="file" name="image" accept="image/*">
      <?php if (!empty($productImage['image_url'])): ?>
        <img class="product-preview"
             src="/clothing_store/uploads/<?= htmlspecialchars($productImage['image_url'], ENT_QUOTES, 'UTF-8') ?>"
             alt="Ảnh sản phẩm">
      <?php endif; ?>

      <label>Tên sản phẩm:</label>
      <input type="text" name="name"
             value="<?= htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <label>Danh mục:</label>
      <select name="category_id">
        <option value="">-- Chọn danh mục --</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>"
            <?= $c['id'] == ($product['category_id'] ?? 0) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Giảm giá (%):</label>
      <input type="number" name="discount_percent"
             value="<?= htmlspecialchars($product['discount_percent'] ?? 0, ENT_QUOTES, 'UTF-8') ?>"
             min="0" max="100" step="0.1">

      <label>Loại sản phẩm:</label>
      <?php $featured = (int)($product['is_featured'] ?? 0); ?>
      <select name="is_featured">
        <option value="0" <?= $featured === 0 ? 'selected' : '' ?>>Sản phẩm thường</option>
        <option value="1" <?= $featured === 1 ? 'selected' : '' ?>>Sản phẩm nổi bật</option>
        <option value="2" <?= $featured === 2 ? 'selected' : '' ?>>Sản phẩm giảm giá</option>
        <option value="3" <?= $featured === 3 ? 'selected' : '' ?>>Sản phẩm mới</option>
      </select>

      <label>Trạng thái kho:</label>
      <?php $st = (int)($product['status'] ?? 1); ?>
      <select name="status">
        <option value="1" <?= $st === 1 ? 'selected' : '' ?>>Còn hàng</option>
        <option value="0" <?= $st === 0 ? 'selected' : '' ?>>Hết hàng</option>
      </select>

      <label>Mô tả:</label>
      <textarea name="description" rows="4"><?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

      <button type="submit">Cập nhật sản phẩm</button>
    </form>

    <!-- FORM BIẾN THỂ -->
    <form method="post" style="margin-top:40px;">
      <input type="hidden" name="update_variants" value="1">

      <div class="variants-wrapper">
        <div class="variants-title">Biến thể sản phẩm</div>
        <div class="variants-sub">Chỉnh sửa size, màu sắc, giá và số lượng cho từng biến thể.</div>

        <table id="variants-table">
          <tr>
            <th>ID</th>
            <th>Size</th>
            <th>Màu</th>
            <th>Giá</th>
            <th>Giá đã giảm</th>
            <th>Số lượng</th>
            <th>Hành động</th>
          </tr>

          <?php foreach ($variants as $v): ?>
            <tr>
              <td><?= (int)$v['id'] ?></td>

              <td>
                <select name="variants[<?= (int)$v['id'] ?>][size_id]">
                  <?php foreach ($sizes as $s): ?>
                    <option value="<?= $s['id'] ?>"
                        <?= $s['id'] == ($v['size_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>

                  <?php endforeach; ?>
                </select>
              </td>

              <td>
                <select name="variants[<?= (int)$v['id'] ?>][color_id]">
                  <?php foreach ($colors as $c): ?>
                    <option value="<?= $c['id'] ?>"
                      <?= $c['id'] == ($v['color_id'] ?? 0) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>

              <td>
                <input type="number"
                       name="variants[<?= (int)$v['id'] ?>][price]"
                       value="<?= htmlspecialchars($v['price'] ?? 0, ENT_QUOTES, 'UTF-8') ?>"
                       step="0.01">
              </td>

              <td>
                <input type="number"
                       name="variants[<?= (int)$v['id'] ?>][price_reduced]"
                       value="<?= htmlspecialchars($v['price_reduced'] ?? 0, ENT_QUOTES, 'UTF-8') ?>"
                       step="0.01">
              </td>

              <td>
                <input type="number"
                       name="variants[<?= (int)$v['id'] ?>][quantity]"
                       value="<?= htmlspecialchars($v['quantity'] ?? 0, ENT_QUOTES, 'UTF-8') ?>">
              </td>

              <td class="variant-action">
                <button type="submit"
                        name="delete_variant"
                        value="<?= (int)$v['id'] ?>"
                        class="btn-inline btn-delete-variant">
                  Xóa
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>

        <button type="submit">Cập nhật biến thể</button>
      </div>
    </form>

    <div class="back-link">
      <a href="products.php">← Quay lại danh sách sản phẩm</a>
    </div>
  </div>

</div>
</body>
</html>
