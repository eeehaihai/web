<?php
$host = '127.0.0.1'; // 或 'localhost'
$db   = 'webdb'; // 您的数据库名称
$user = 'web';     // 您的数据库用户名
$pass = '088611';     // 您的数据库密码
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // 在生产环境中，您可能希望记录此错误而不是显示它
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '数据库连接失败: ' . $e->getMessage()]);
    exit;
}
?>
