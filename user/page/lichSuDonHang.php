<?php
session_start();
require './connect-db.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}

$uid = (int)$_SESSION['customer_id'];
$global_msg = ''; // Thông báo tổng thể

// Xử lý gửi phản hồi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $order_id = (int)$_POST['order_id'];
    $message = trim($_POST['message']);

    if ($message === '') {
        $global_msg = "Vui lòng nhập nội dung phản hồi.";
    } else {
        // Kiểm tra đơn hàng hoàn thành
        $stmt = $conn->prepare("
            SELECT created_at FROM orders 
            WHERE order_id = ? AND customer_id = ? AND order_status = 'completed'
        ");
        $stmt->bind_param("ii", $order_id, $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $order = $res->fetch_assoc();
        $stmt->close();

        if (!$order) {
            $global_msg = "Đơn hàng không tồn tại hoặc chưa hoàn thành.";
        } else {
            // Kiểm tra thời gian phản hồi <= 2 ngày
            $diff_days = (time() - strtotime($order['created_at'])) / (60*60*24);
            if ($diff_days > 2) {
                $global_msg = "Quá 2 ngày kể từ khi hoàn thành đơn hàng. Không thể gửi phản hồi.";
            } else {
                // Kiểm tra xem đơn hàng đã phản hồi chưa
                $stmt = $conn->prepare("
                    SELECT feedback_id FROM feedbacks 
                    WHERE order_id = ? AND customer_id = ?
                ");
                $stmt->bind_param("ii", $order_id, $uid);
                $stmt->execute();
                $res = $stmt->get_result();
                $existing_feedback = $res->fetch_assoc();
                $stmt->close();

                if ($existing_feedback) {
                    $global_msg = "Đơn hàng này bạn đã phản hồi trước đó.";
                } else {
                    // Thêm phản hồi
                    $stmt = $conn->prepare("
                        INSERT INTO feedbacks (customer_id, order_id, message, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("iis", $uid, $order_id, $message);
                    $stmt->execute();
                    $stmt->close();
                    $global_msg = "Gửi phản hồi thành công!";
                }
            }
        }
    }
}

// Lấy danh sách đơn hàng
$orders = [];
$stmt = $conn->prepare("SELECT order_id, total_amount, order_status, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Lịch sử đơn hàng - CakeZone</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="../../assets/img/favicon.ico" rel="icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .border-inner { border:1px solid rgba(255,255,255,0.06); }
        .status-badge.pending { background:#ffc107; color:#000; }
        .status-badge.completed { background:#198754; color:#fff; }
        .status-badge.cancelled { background:#dc3545; color:#fff; }
        .feedback-box { background:#343a40; padding:10px; margin-top:10px; border-radius:5px; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container-fluid py-5 mb-5 hero-header">
    <div class="container py-5">
        <div class="row justify-content-start">
            <div class="col-lg-8 text-center text-lg-start">
                <h1 class="font-secondary text-primary mb-4">My Orders</h1>
                <h1 class="display-1 text-uppercase text-white mb-4">Lịch Sử Đơn Hàng</h1>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <?php if($global_msg): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <?= htmlspecialchars($global_msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <div class="bg-dark border-inner p-4 rounded">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="text-white mb-0">Đơn hàng của tôi</h4>
                    <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="fa fa-arrow-left"></i> Tiếp tục mua sắm</a>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="text-center text-secondary py-5">
                        <p class="mb-0">Bạn chưa có đơn hàng nào.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Ngày</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Tổng tiền</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): 
                                    $oid = (int)$order['order_id'];
                                    $status = strtolower($order['order_status']);
                                    $order_completed = $status === 'completed';
                                    $days_diff = $order_completed ? (time() - strtotime($order['created_at'])) / (60*60*24) : 0;
                                    $can_feedback = false;

                                    $feedback_text = '';
                                    if($order_completed) {
                                        // Kiểm tra phản hồi
                                        $stmt_fb = $conn->prepare("SELECT message, created_at FROM feedbacks WHERE order_id = ? AND customer_id = ?");
                                        $stmt_fb->bind_param("ii", $oid, $uid);
                                        $stmt_fb->execute();
                                        $res_fb = $stmt_fb->get_result();
                                        $fb = $res_fb->fetch_assoc();
                                        if($fb) {
                                            $feedback_text = $fb['message'];
                                        } elseif ($days_diff <= 2) {
                                            $can_feedback = true;
                                        }
                                        $stmt_fb->close();
                                    }
                                ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($oid) ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                                        <td>
                                            <span class="badge <?=
                                                $status === 'completed' ? 'status-badge completed' :
                                                ($status === 'pending' ? 'status-badge pending' : 'status-badge cancelled')
                                            ?>">
                                                <?= htmlspecialchars(ucfirst($order['order_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?= number_format($order['total_amount'],0,',','.') ?>₫</td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#items-<?= $oid ?>" aria-expanded="false">
                                                Xem chi tiết
                                            </button>
                                            <?php if ($can_feedback): ?>
                                                <button class="btn btn-sm btn-outline-info ms-1" data-bs-toggle="collapse" data-bs-target="#feedback-<?= $oid ?>" aria-expanded="false">
                                                    Phản hồi
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <!-- Chi tiết đơn hàng -->
                                    <tr class="collapse-row">
                                        <td colspan="5" class="p-0">
                                            <div class="collapse" id="items-<?= $oid ?>">
                                                <div class="p-3 bg-secondary">
                                                    <table class="table table-sm table-dark mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Sản phẩm</th>
                                                                <th class="text-center">Số lượng</th>
                                                                <th class="text-end">Đơn giá</th>
                                                                <th class="text-end">Thành tiền</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $stmt2 = $conn->prepare("
                                                                SELECT oi.quantity, oi.price, p.product_name
                                                                FROM order_items oi
                                                                JOIN products p ON oi.product_id = p.product_id
                                                                WHERE oi.order_id = ?
                                                            ");
                                                            $stmt2->bind_param("i", $oid);
                                                            $stmt2->execute();
                                                            $res2 = $stmt2->get_result();
                                                            $sum = 0;
                                                            while ($it = $res2->fetch_assoc()):
                                                                $line = $it['price'] * $it['quantity'];
                                                                $sum += $line;
                                                            ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($it['product_name']) ?></td>
                                                                    <td class="text-center"><?= (int)$it['quantity'] ?></td>
                                                                    <td class="text-end"><?= number_format($it['price'],0,',','.') ?>₫</td>
                                                                    <td class="text-end"><?= number_format($line,0,',','.') ?>₫</td>
                                                                </tr>
                                                            <?php endwhile;
                                                            $stmt2->close();
                                                            ?>
                                                            <tr>
                                                                <td colspan="3" class="text-end"><strong>Tổng</strong></td>
                                                                <td class="text-end"><strong><?= number_format($sum,0,',','.') ?>₫</strong></td>
                                                            </tr>
                                                            <?php if($feedback_text): ?>
                                                                <tr>
                                                                    <td colspan="4">
                                                                        <div class="feedback-box text-light">
                                                                            <strong>Phản hồi của bạn:</strong><br>
                                                                            <?= nl2br(htmlspecialchars($feedback_text)) ?>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Form phản hồi -->
                                    <?php if ($can_feedback): ?>
                                        <tr class="collapse-row">
                                            <td colspan="5" class="p-0">
                                                <div class="collapse" id="feedback-<?= $oid ?>">
                                                    <div class="p-3 bg-secondary">
                                                        <form method="post">
                                                            <input type="hidden" name="order_id" value="<?= $oid ?>">
                                                            <div class="mb-2">
                                                                <textarea class="form-control" name="message" rows="3" placeholder="Nhập phản hồi..." required></textarea>
                                                            </div>
                                                            <button type="submit" name="submit_feedback" class="btn btn-sm btn-primary">Gửi phản hồi</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
<?php $conn->close(); ?>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
