<?php
session_name('admin_session');
session_start();
header('Content-Type: application/json; charset=utf-8');

// 🛑 Chỉ cho admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Không có quyền thực hiện"]);
    exit;
}

// 🛑 Kiểm tra method & dữ liệu
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Phương thức không hợp lệ"]);
    exit;
}

if (empty($_POST['id']) || !isset($_POST['status'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Thiếu ID hoặc trạng thái"]);
    exit;
}

$id        = (int) $_POST['id'];
$newStatus = trim($_POST['status']);

try {
    require_once __DIR__ . '/../config/db.php'; // tạo $pdo

    // ✅ Lấy trạng thái hiện tại
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Không tìm thấy đơn hàng"]);
        exit;
    }

    $currentStatus = $order['status'];

    // ✅ Danh sách trạng thái hợp lệ cho luồng
    $statusFlow = [
        'Chờ xác nhận',
        'Đang xử lý',
        'Đơn hàng đang được giao',
        'Đã giao hàng',
        'Hủy đơn hàng'
    ];

    $inCurrent = in_array($currentStatus, $statusFlow, true);
    $inNew     = in_array($newStatus, $statusFlow, true);

    // ❗ Nếu cả trạng thái cũ & mới đều thuộc flow → kiểm tra bước
    if ($inCurrent && $inNew) {
        $currentIndex = array_search($currentStatus, $statusFlow, true);
        $newIndex     = array_search($newStatus, $statusFlow, true);

        // Chỉ cho phép sang bước kế tiếp hoặc hủy
        if ($newStatus !== 'Hủy đơn hàng' && $newIndex !== $currentIndex + 1) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Chỉ được sang bước kế tiếp hoặc hủy đơn hàng"
            ]);
            exit;
        }
    }
    // ⚠ Nếu status cũ không nằm trong list → bỏ qua check, vẫn cho update

    // ✅ Cập nhật trạng thái
    $update = $pdo->prepare("UPDATE orders SET status = :st WHERE id = :id");
    $update->execute([
        ':st' => $newStatus,
        ':id' => $id
    ]);

    echo json_encode([
        "success"   => true,
        "message"   => "Cập nhật thành công",
        "newStatus" => $newStatus,
        "oldStatus" => $currentStatus
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi CSDL: " . $e->getMessage()]);
}
