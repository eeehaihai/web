<?php
// 设置响应类型为JSON
header('Content-Type: application/json');

// 引入数据库连接
require_once 'db.php'; 
// 引入工具函数
require_once 'utils.php';

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $pdo; // 使用来自 db.php 的 PDO 对象

    // 获取用户提交的注册数据
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    // 简单验证
    if (empty($username) || empty($password) || empty($email) || empty($phone)) { 
        jsonResponse([
            'success' => false,
            'message' => '请填写所有必填字段'
        ]);
    }
    
    // 验证用户名格式
    if (!preg_match('/^[a-zA-Z0-9_]{5,20}$/', $username)) {
        jsonResponse([
            'success' => false,
            'message' => '用户名必须是5-20位字母、数字或下划线'
        ]);
    }
    
    // 验证密码长度
    if (strlen($password) < 8) {
        jsonResponse([
            'success' => false,
            'message' => '密码长度必须大于或等于8位'
        ]);
    }
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse([
            'success' => false,
            'message' => '请输入有效的电子邮箱地址'
        ]);
    }
    
    // 验证手机号格式
    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        jsonResponse([
            'success' => false,
            'message' => '请输入有效的11位手机号码'
        ]);
    }

    try {
        // 检查用户名是否已存在
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            jsonResponse([
                'success' => false,
                'message' => '用户名已被使用'
            ]);
        }

        // 检查邮箱是否已存在
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse([
                'success' => false,
                'message' => '电子邮箱已被注册'
            ]);
        }
        
        // 检查手机号是否已存在 (如果 phone 字段是 UNIQUE)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            jsonResponse([
                'success' => false,
                'message' => '手机号码已被注册'
            ]);
        }

        // 哈希密码
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 添加新用户
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$username, $hashedPassword, $email, $phone])) {
            jsonResponse([
                'success' => true,
                'message' => '注册成功'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => '注册失败，无法保存用户数据'
            ]);
        }
    } catch (PDOException $e) {
        jsonResponse([
            'success' => false,
            'message' => '数据库操作失败: ' . $e->getMessage() // 开发时显示，生产环境应记录日志
        ]);
    }
    
} else {
    // 非POST请求返回错误
    jsonResponse([
        'success' => false,
        'message' => '请求方法不允许'
    ]);
}
?>
