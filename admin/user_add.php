<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php'; // db.php tạo ra $pdo

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$errors = [];

// Giá trị mặc định để giữ lại form khi lỗi
$username = '';
$fullname ='';
$mobile='';
$email    = '';
$sex      = 1;         // mặc định Nam
$role     = 'User';    // mặc định người dùng
$active   = 1;         // mặc định hoạt động
$address  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $fullname  = trim($_POST['fullname'] ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';
    $email     = trim($_POST['email'] ?? '');
    $mobile    = trim($_POST['mobile'] ?? '');
    $sex       = isset($_POST['sex']) ? (int)$_POST['sex'] : 1;
    $role      = $_POST['role'] ?? 'User';   // 'Admin' / 'User'
    $active    = isset($_POST['active']) ? 1 : 0;
    $address   = trim($_POST['address'] ?? '');

    // --- Validate cơ bản ---
    if ($username === '')  $errors[] = "Vui lòng nhập tên đăng nhập.";
    if ($email === '')     $errors[] = "Vui lòng nhập email.";
    if ($password === '')  $errors[] = "Vui lòng nhập mật khẩu.";
    if ($password !== $password2) {
        $errors[] = "Mật khẩu xác nhận không khớp.";
    }

    // --- Kiểm tra trùng username ---
    if ($username !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Tên đăng nhập đã tồn tại.";
        }
    }

    // --- Kiểm tra trùng email ---
    if ($email !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email đã tồn tại.";
        }
    }
    // --- Kiểm tra trùng fullname ---
    if ($fullname !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE fullname = ?");
        $stmt->execute([$fullname]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Họ và tên đã được sử dụng.";
        }
    }

    // --- Kiểm tra trùng số điện thoại ---
    if ($mobile !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
        $stmt->execute([$mobile]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Số điện thoại đã tồn tại.";
        }
    }

    // --- Nếu không có lỗi thì thêm user + địa chỉ ---
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            // 1. Thêm user
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, email, fullname, mobile, sex, role, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $hash, $email, $fullname, $mobile, $sex, $role, $active]);

            $userId = $pdo->lastInsertId();

            // 2. Nếu có nhập địa chỉ -> thêm vào user_address + cập nhật address_id
            if ($address !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO user_address (address, `default`, user_id)
                    VALUES (?, 0, ?)
                ");
                $stmt->execute([$address, $userId]);

                $addressId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("UPDATE users SET address_id = ? WHERE id = ?");
                $stmt->execute([$addressId, $userId]);
            }

            $pdo->commit();
            header("Location: users.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Có lỗi khi lưu dữ liệu: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm người dùng</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }
        body {
            display: flex;
            background: #f9f9f9;
            color: #333;
        }

        /* SIDEBAR – đồng bộ với các trang admin khác */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: #fff;
            padding: 30px 20px;
            position: fixed;
            border-right: 1px solid #ddd;
        }
        .sidebar h3 {
            color: #222;
            margin-bottom: 25px;
            font-weight: 700;
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
            padding: 12px 14px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 15px;
            transition: 0.25s;
        }
        .sidebar a:hover {
            color: #8E5DF5;
            background: #f0e8ff;
            transform: translateX(5px);
        }

        /* Content */
        .content {
            margin-left: 280px;
            padding: 40px;
            width: calc(100% - 280px);
            min-height: 100vh;
        }
        h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        p.subtitle {
            color: #aaa;
            margin-bottom: 20px;
        }

        /* Form */
        form {
            background: #fff;
            padding: 40px;
            border-radius: 14px;
            border: 1px solid #e5e5e5;
            max-width: 700px;
            margin: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        h2 {
            text-align: center;
            color: #8E5DF5;
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: 700;
        }
        .form-control,
        .form-select,
        textarea.form-control {
            width: 100%;
            background: #fff;
            color: #333;
            border: 1px solid #dcdcdc;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 16px;
            transition: border-color 0.3s;
        }
        .form-control:focus,
        .form-select:focus,
        textarea.form-control:focus {
            border-color: #8E5DF5;
            outline: none;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .form-check-input {
            accent-color: #8E5DF5;
        }

        /* Button */
        .btn-success {
            background: #8E5DF5;
            color: #fff;
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-success:hover {
            background: #a57bff;
        }

        /* Error message */
        .form-error {
            background: #ffe8e8;
            color: #d40000;
            border: 1px solid #ffb3b3;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .form-error p {
            margin-bottom: 4px;
        }

        /* Link quay lại – đồng bộ với news_add, news_edit */
        .back-link {
            margin-top: 14px;
            font-size: 14px;
        }
        .back-link a {
            color: #8E5DF5;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
            color: #E91E63;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .content {
                margin-left: 0;
                padding: 20px;
                width: 100%;
            }
            form {
                padding: 25px;
            }
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
    <h1>Thêm người dùng mới</h1>
    <p class="subtitle">Điền thông tin bên dưới để tạo tài khoản người dùng mới.</p>

    <form method="post">
        <h2>Thông tin tài khoản</h2>

        <?php if (!empty($errors)): ?>
            <div class="form-error">
                <?php foreach ($errors as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <input
            name="username"
            class="form-control"
            placeholder="Tên đăng nhập"
            required
            value="<?= htmlspecialchars($username) ?>"
        >
        <input
            name="fullname"
            class="form-control"
            placeholder="Họ và tên"
            required
            value="<?= htmlspecialchars($fullname) ?>"
        >
        <input
            type="password"
            name="password"
            class="form-control"
            placeholder="Mật khẩu"
            required
        >

        <input
            type="password"
            name="password2"
            class="form-control"
            placeholder="Xác nhận mật khẩu"
            required
        >
        <input
            name="mobile"
            class="form-control"
            placeholder="Số điện thoại"
            required
            value="<?= htmlspecialchars($mobile) ?>"
        >

        <input
            name="email"
            type="email"
            class="form-control"
            placeholder="Email"
            value="<?= htmlspecialchars($email) ?>"
        >

        <textarea
            name="address"
            class="form-control"
            placeholder="Địa chỉ (số nhà, đường, quận...)"
            rows="3"
        ><?= htmlspecialchars($address) ?></textarea>

        <select name="sex" class="form-select">
            <option value="0" <?= $sex == 0 ? 'selected' : '' ?>>Nữ</option>
            <option value="1" <?= $sex == 1 ? 'selected' : '' ?>>Nam</option>
        </select>

        <select name="role" class="form-select">
            <option value="User"  <?= $role === 'User'  ? 'selected' : '' ?>>Người dùng</option>
            <option value="Admin" <?= $role === 'Admin' ? 'selected' : '' ?>>Admin</option>
        </select>

        <div class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                name="active"
                id="active"
                <?= $active ? 'checked' : '' ?>
            >
            <label for="active">Hoạt động</label>
        </div>

        <button class="btn-success">Lưu người dùng</button>
        <div class="back-link">
            <a href="users.php">← Quay lại danh sách người dùng</a>
        </div>
    </form>

</div>

</body>
</html>
