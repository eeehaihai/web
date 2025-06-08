<?php
// 开启会话
session_start();

// 清除所有会话变量
$_SESSION = array();

// 如果要彻底销毁会话，还要删除会话cookie
// 注意：这会同时销毁会话中的所有数据！
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 最后，销毁会话
session_destroy();

// 设置响应类型为JSON
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>
