<?php
// Autoload của Composer (bắt buộc)
require_once __DIR__ . '/../../vendor/autoload.php';


use Mpdf\Mpdf; // thêm dòng này để dùng class Mpdf

// ---- LẤY ORDER ID ----
$order_id = $_GET['order_id'] ?? 0;

// ---- GIẢ LẬP DỮ LIỆU HOÁ ĐƠN (thay bằng dữ liệu từ DB của bạn) ----
$order = [
    "id" => $order_id,
    "date" => "22/11/2025 16:56",
    "status" => "Completed",
    "total" => "300.000đ",
    "items" => [
        ["name" => "Kem", "qty" => 1, "price" => "300.000đ", "amount" => "300.000đ"]
    ]
];

// ---- TẠO HTML HOÁ ĐƠN ----
$html = '
<h2 style="text-align:center;">HOÁ ĐƠN THANH TOÁN</h2>
<p><strong>Mã đơn:</strong> #' . $order["id"] . '</p>
<p><strong>Ngày:</strong> ' . $order["date"] . '</p>
<p><strong>Trạng thái:</strong> ' . $order["status"] . '</p>

<table border="1" width="100%" cellpadding="8" cellspacing="0">
    <tr>
        <th>Sản phẩm</th>
        <th>Số lượng</th>
        <th>Đơn giá</th>
        <th>Thành tiền</th>
    </tr>';

foreach ($order["items"] as $item) {
    $html .= '
    <tr>
        <td>' . $item["name"] . '</td>
        <td>' . $item["qty"] . '</td>
        <td>' . $item["price"] . '</td>
        <td>' . $item["amount"] . '</td>
    </tr>
    ';
}

$html .= '
</table>

<h3 style="text-align:right;">Tổng: ' . $order["total"] . '</h3>
';

// ---- TẠO PDF ----
$mpdf = new Mpdf();
$mpdf->WriteHTML($html);

// ---- TẢI VỀ PDF ----
$mpdf->Output('hoa-don-' . $order_id . '.pdf', 'D');
exit;
?>
