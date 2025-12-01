<?php
session_name('admin_session');
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

// Lấy danh mục, size, color
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")
                      ->fetchAll(PDO::FETCH_ASSOC);

    $sizes = $pdo->query("SELECT id, name FROM sizes ORDER BY id ASC")
                 ->fetchAll(PDO::FETCH_ASSOC);

    $colors = $pdo->query("SELECT id, name FROM colors ORDER BY name ASC")
                  ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Lỗi khi lấy dữ liệu: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $variants    = $_POST['variants'] ?? [];

    // kiểm tra biến thể có size_id & color_id hợp lệ không
    $hasValidVariant = false;
    foreach ($variants as $v) {
        $size_id  = (int)($v['size_id'] ?? 0);
        $color_id = (int)($v['color_id'] ?? 0);
        if ($size_id > 0 && $color_id > 0) {
            $hasValidVariant = true;
            break;
        }
    }

    if ($name === '' || $description === '' || $category_id <= 0 || !$hasValidVariant) {
        $error = "Vui lòng điền đầy đủ thông tin sản phẩm và ít nhất 1 biến thể hợp lệ.";
    }

    // xử lý ảnh
    $imagePath = '';
    if (!$error) {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = "Vui lòng chọn ảnh sản phẩm.";
        } else {
            $imageName = $_FILES['image']['name'];
            $imageTmp  = $_FILES['image']['tmp_name'];

            // thư mục lưu ảnh (clothing_store/uploads)
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($imageName, PATHINFO_EXTENSION);
            $safeName  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($imageName, PATHINFO_FILENAME));
            $imagePath = uniqid('', true) . '_' . $safeName . '.' . $ext;

            $destination = $uploadDir . $imagePath;
            if (!move_uploaded_file($imageTmp, $destination)) {
                $error = "Không thể lưu ảnh lên máy chủ.";
            }
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // Thêm sản phẩm (bảng products không có cột image)
            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, category_id)
                VALUES (:name, :description, :category_id)
            ");
            $stmt->execute([
                ':name'        => $name,
                ':description' => $description,
                ':category_id' => $category_id,
            ]);
            $productId = (int)$pdo->lastInsertId();

            // Lưu ảnh chính vào product_images
            $stmtImg = $pdo->prepare("
                INSERT INTO product_images (product_id, image_url)
                VALUES (:product_id, :image_url)
            ");
            $stmtImg->execute([
                ':product_id' => $productId,
                ':image_url'  => $imagePath,
            ]);

            // Thêm biến thể
            $stmtVar = $pdo->prepare("
                INSERT INTO product_variants
                    (product_id, size_id, color_id, quantity, price, price_reduced)
                VALUES
                    (:product_id, :size_id, :color_id, :quantity, :price, :price_reduced)
            ");

            foreach ($variants as $v) {
                $size_id       = (int)($v['size_id'] ?? 0);
                $color_id      = (int)($v['color_id'] ?? 0);
                $quantity      = (int)($v['quantity'] ?? 0);
                $price         = (float)($v['price'] ?? 0);
                $price_reduced = (float)($v['price_reduced'] ?? 0);

                if ($size_id > 0 && $color_id > 0) {
                    $stmtVar->execute([
                        ':product_id'     => $productId,
                        ':size_id'        => $size_id,
                        ':color_id'       => $color_id,
                        ':quantity'       => $quantity,
                        ':price'          => $price,
                        ':price_reduced'  => $price_reduced,
                    ]);
                }
            }

            $pdo->commit();
            header("Location: products.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Lỗi khi lưu sản phẩm: " . $e->getMessage();
        }
    }
}

// build options size & color để dùng cho JS
$sizeOptionsHtml = '';
foreach ($sizes as $s) {
    $sizeOptionsHtml .= '<option value="'.$s['id'].'">'.htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8').'</option>';
}
$colorOptionsHtml = '';
foreach ($colors as $c) {
    $colorOptionsHtml .= '<option value="'.$c['id'].'">'.htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8').'</option>';
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<title>Thêm sản phẩm - YaMy Admin</title>
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
.sidebar h3{
  color:#111;margin-bottom:25px;font-weight:700;font-size:20px;
  text-transform:uppercase;letter-spacing:2px;
}
.sidebar a{
  display:flex;align-items:center;gap:10px;color:#111;
  padding:12px 14px;border-radius:8px;font-weight:500;
  transition:.25s;font-size:15px;
}
.sidebar a:hover{background:#f0f0f0;transform:translateX(5px);}
.sidebar a.logout{color:#ff4d4d;margin-top:40px;}
.content{
  margin-left:280px;padding:40px;width:calc(100% - 280px);min-height:100vh;
}
h2{
  text-align:center;color:#8E5DF5;margin-bottom:25px;
  font-size:26px;font-weight:700;
}
.form-card{
  background:#fff;border-radius:16px;padding:40px;max-width:900px;
  margin:auto;box-shadow:0 2px 12px rgba(0,0,0,.08);
}
label{
  display:block;margin-top:18px;font-weight:600;color:#444;
}
input[type="text"],
input[type="number"],
textarea,
select,
input[type="file"]{
  width:100%;margin-top:6px;padding:12px 14px;border:1px solid #ddd;
  border-radius:10px;background:#fff;font-size:15px;transition:.3s;
}
input:focus,textarea:focus,select:focus{
  border-color:#8E5DF5;outline:none;
}
textarea{resize:vertical;min-height:90px;}
fieldset{
  margin-top:25px;padding:20px;border:1px solid #ddd;
  border-radius:12px;background:#fafafa;
}
legend{
  color:#8E5DF5;font-weight:700;padding:0 8px;
}
button{
  margin-top:20px;padding:10px 20px;border:none;border-radius:10px;
  background:#E91E63;color:#fff;font-weight:700;font-size:16px;
  cursor:pointer;transition:.25s;
}
button:hover{background:#ff4081;}
table{
  width:100%;border-collapse:collapse;margin-top:15px;table-layout:auto;
}
table th,table td{
  border:1px solid #ddd;padding:8px;text-align:center;vertical-align:middle;
}
table th{background:#f0f0f0;}
#variants-table th:nth-child(1),
#variants-table td:nth-child(1){
  min-width:100px;white-space:nowrap;font-size:15px;
}
.btn-xoa{
  background:#ff4d4d;border:none;padding:4px 8px;border-radius:4px;
  cursor:pointer;color:#fff;font-size:13px;
}
.btn-xoa:hover{background:#cc0000;}
.add-variant{
  margin-top:10px;padding:6px 12px;background:#8E5DF5;color:#fff;
  border:none;border-radius:6px;cursor:pointer;font-size:14px;
}
.add-variant:hover{background:#7c47e0;}
.back-link{text-align:center;margin-top:20px;}
.back-link a{color:#8E5DF5;font-weight:600;}
.back-link a:hover{text-decoration:underline;}
.error{
  color:#ff4d4d;text-align:center;margin-bottom:10px;font-weight:600;
}
.success{
  color:#2e7d32;text-align:center;margin-bottom:10px;font-weight:600;
}
#variants-table select {
    padding: 10px;
    font-size: 15px;
    min-width: 160px;
}

</style>
<script>
// options size & color sinh từ PHP
const sizeOptions = `<?= $sizeOptionsHtml ?>`;
const colorOptions = `<?= $colorOptionsHtml ?>`;

// thêm hàng biến thể
function addVariantRow() {
  const tbody = document.querySelector('#variants-table tbody');
  const index = tbody.querySelectorAll('tr').length;
  const row = document.createElement('tr');
  row.innerHTML = `
    <td>
      <select name="variants[${index}][size_id]" required>
        <option value="">-- Size --</option>
        ${sizeOptions}
      </select>
    </td>
    <td>
      <select name="variants[${index}][color_id]" required>
        <option value="">-- Màu --</option>
        ${colorOptions}
      </select>
    </td>
    <td><input type="number" name="variants[${index}][quantity]" value="0" min="0" required></td>
    <td><input type="number" name="variants[${index}][price]" value="0" min="0" step="0.01" required></td>
    <td><input type="number" name="variants[${index}][price_reduced]" value="0" min="0" step="0.01" required></td>
    <td><button type="button" class="btn-xoa" onclick="this.closest('tr').remove()">Xóa</button></td>
  `;
  tbody.appendChild(row);
}
</script>
</head>
<body>
<div class="sidebar">
  <h3>YaMy Admin</h3>
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
  <div class="form-card">
    <h2>Thêm sản phẩm mới</h2>

    <?php if (!empty($error)): ?>
      <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <label>Ảnh sản phẩm:</label>
      <input type="file" name="image" accept="image/*" required>

      <label>Tên sản phẩm:</label>
      <input type="text" name="name" placeholder="Ví dụ: Áo Thun Logo YaMy" required>

      <label>Danh mục:</label>
      <select name="category_id" required>
        <option value="">-- Chọn danh mục --</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>">
            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Mô tả sản phẩm:</label>
      <textarea name="description" rows="4" placeholder="Nhập mô tả chi tiết" required></textarea>

      <fieldset>
        <legend>Biến thể (Size, Màu, Tồn kho & Giá)</legend>
        <table id="variants-table">
          <thead>
            <tr>
              <th>Size</th>
              <th>Màu</th>
              <th>Tồn kho</th>
              <th>Giá (VND)</th>
              <th>Giá đã giảm (VND)</th>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <select name="variants[0][size_id]" required>
                  <option value="">-- Size --</option>
                  <?php foreach ($sizes as $s): ?>
                    <option value="<?= $s['id'] ?>">
                      <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <select name="variants[0][color_id]" required>
                  <option value="">-- Màu --</option>
                  <?php foreach ($colors as $c): ?>
                    <option value="<?= $c['id'] ?>">
                      <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="variants[0][quantity]" value="0" min="0" required></td>
              <td><input type="number" name="variants[0][price]" value="0" min="0" step="0.01" required></td>
              <td><input type="number" name="variants[0][price_reduced]" value="0" min="0" step="0.01" required></td>
              <td><button type="button" class="btn-xoa" onclick="this.closest('tr').remove()">Xóa</button></td>
            </tr>
          </tbody>
        </table>
        <button type="button" class="add-variant" onclick="addVariantRow()">+ Thêm biến thể</button>
      </fieldset>

      <button type="submit">Thêm sản phẩm</button>
    </form>

    <div class="back-link">
      <a href="products.php">← Quay lại danh sách sản phẩm</a>
    </div>
  </div>
</div>
</body>
</html>
