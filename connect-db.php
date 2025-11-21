<?php
// Kết nối mà không chỉ định DB trước
$conn = new mysqli('localhost', 'root', '', '');

if ($conn->connect_error) {
    die('Kết nối thất bại: ' . $conn->connect_error);
}

// Tạo database nếu chưa tồn tại
$conn->query('CREATE DATABASE IF NOT EXISTS minhnhi_db');
$conn->select_db('minhnhi_db');

echo "<hr>";
echo "Database minhnhi_db sẵn sàng!";
$conn->close();
?>