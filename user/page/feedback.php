<?php
session_start();
require './connect-db.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}

$uid = (int)$_SESSION['customer_id'];
$msg = '';

// Xử lý gửi phản hồi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $order_id = (int)$_POST['order_id'];
    $message = trim($_POST['message']);

    if ($message === '') {
        $msg = "Vui lòng nhập nội dung phản hồi.";
    } else {
        // Kiểm tra đơn hàng có được phản hồi hay không (completed trong 2 ngày)
        $stmt = $conn->prepare("
            SELECT created_at 
            FROM orders 
            WHERE order_id = ? AND customer_id = ? AND order_status = 'completed'
        ");
        $stmt->bind_param("ii", $order_id, $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $order = $res->fetch_assoc();
        $stmt->close();

        if (!$order) {
            $msg = "Đơn hàng không tồn tại hoặc chưa hoàn thành.";
        } else {
            $completed_time = strtotime($order['created_at']);
            $now = time();
            $diff_days = ($now - $completed_time) / (60*60*24);

            if ($diff_days > 2) {
                $msg = "Quá 2 ngày kể từ khi hoàn thành đơn hàng. Không thể gửi phản hồi.";
            } else {
                // Chèn phản hồi
                $stmt = $conn->prepare("
                    INSERT INTO feedbacks (customer_id, order_id, message, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->bind_param("iis", $uid, $order_id, $message);
                $stmt->execute();
                $stmt->close();

                $msg = "Gửi phản hồi thành công!";
            }
        }
    }
}

// Lấy danh sách đơn hàng completed
$orders = [];
$stmt = $conn->prepare("
    SELECT order_id, total_amount, created_at 
    FROM orders 
    WHERE customer_id = ? AND order_status = 'completed'
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Phản hồi đơn hàng - CakeZone</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="bg-dark border-inner p-5 rounded">
                <h3 class="text-white mb-4"><i class="fa fa-comment-dots text-primary"></i> Gửi Phản Hồi</h3>

                <?php if ($msg): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($orders)): ?>
                    <p class="text-white">Bạn chưa có đơn hàng hoàn thành nào để phản hồi.</p>
                <?php else: ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="order_id" class="form-label text-white">Chọn đơn hàng</label>
                            <select class="form-select" id="order_id" name="order_id" required>
                                <option value="">-- Chọn đơn hàng --</option>
                                <?php foreach ($orders as $o): 
                                    $completed_time = strtotime($o['created_at']);
                                    $diff_days = (time() - $completed_time) / (60*60*24);
                                    $disabled = $diff_days > 2 ? 'disabled' : '';
                                    $label = "#" . $o['order_id'] . " - " . number_format($o['total_amount'],0,',','.') . "₫ - " . date('d/m/Y', strtotime($o['created_at']));
                                    if ($diff_days > 2) $label .= " (quá hạn phản hồi)";
                                ?>
                                    <option value="<?= $o['order_id'] ?>" <?= $disabled ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label text-white">Nội dung phản hồi</label>
                            <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                        </div>

                        <button type="submit" name="submit_feedback" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Gửi phản hồi</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
