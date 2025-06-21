<?php
// 开启会话
session_start();

// 设置响应类型为JSON
header('Content-Type: application/json');

// 引入数据库连接
require_once 'db.php';
// 引入工具函数
require_once 'utils.php';

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $pdo;

    // 获取用户提交的数据
    $usernameOrEmail = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // 简单验证
    if (empty($usernameOrEmail) || empty($password)) {
        jsonResponse([
            'success' => false,
            'message' => '用户名/邮箱和密码不能为空'
        ]);
    }
    
    try {
        // 检查是用户名还是邮箱
        $field = filter_var($usernameOrEmail, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE $field = ?");
        $stmt->execute([$usernameOrEmail]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // 密码匹配，登录成功
            $_SESSION['login'] = $user['username']; // 存储用户名到会话
            $_SESSION['login_user_id'] = $user['id']; // 存储用户ID到会话
            
            jsonResponse([
                'success' => true,
                'message' => '登录成功',
                'username' => $user['username'] // 可以选择返回用户名给前端
            ]);
        } else {
            // 用户不存在或密码错误
            jsonResponse([
                'success' => false,
                'message' => '用户名/邮箱或密码错误'
            ]);
        }
    } catch (PDOException $e) {
        jsonResponse([
            'success' => false,
            'message' => '数据库操作失败: ' . $e->getMessage() // 开发时显示
        ]);
    }
} else {
    // 非POST请求返回错误
    jsonResponse([
        'success' => false,
        'message' => '请求方法不允许'
    ]);
}


