<?php
// 开启会话
session_start();

// 设置响应类型为JSON
header('Content-Type: application/json');

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取用户提交的数据
    $usernameOrEmail = isset($_POST['username']) ? $_POST['username'] : ''; // 字段名保持 'username'，但值可以是用户名或邮箱
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // 简单验证
    if (empty($usernameOrEmail) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => '用户名/邮箱和密码不能为空'
        ]);
        exit;
    }
    
    // 读取用户数据文件
    $usersFile = 'users.json';
    if (!file_exists($usersFile)) {
        echo json_encode([
            'success' => false,
            'message' => '用户数据文件不存在'
        ]);
        exit;
    }
    
    $usersData = file_get_contents($usersFile);
    $users = json_decode($usersData, true);
    
    // 验证用户
    $authenticated = false;
    $loggedInUsername = ''; // 存储成功登录的用户名

    if (isset($users['users']) && is_array($users['users'])) { // 确保 users 结构正确
        foreach ($users['users'] as $user) {
            // 检查用户名或邮箱是否匹配，然后验证密码
            if (($user['username'] === $usernameOrEmail || (isset($user['email']) && $user['email'] === $usernameOrEmail)) && $user['password'] === $password) { // 注意：实际项目中密码应哈希比较
                $authenticated = true;
                $loggedInUsername = $user['username']; // 存储实际的用户名以用于会话
                break;
            }
        }
    }
    
    // 处理验证结果
    if ($authenticated) {
        // 设置会话
        $_SESSION['login'] = $loggedInUsername; // 使用用户名设置会话
        
        echo json_encode([
            'success' => true,
            'message' => '登录成功'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '用户名/邮箱或密码错误'
        ]);
    }
} else {
    // 非POST请求返回错误
    echo json_encode([
        'success' => false,
        'message' => '请求方法不允许'
    ]);
}
?>
