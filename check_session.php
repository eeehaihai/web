<?php
session_start();
require_once 'db.php'; // 确保 $pdo 已定义

header('Content-Type: application/json');

if (isset($_SESSION['login']) && !empty($_SESSION['login']) && isset($_SESSION['login_user_id'])) {
    global $pdo;
    // 验证 session 中的 user_id 和 username 是否对应数据库中的有效用户
    $stmtVerify = $pdo->prepare("SELECT id, username, nickname FROM users WHERE id = ? AND username = ?");
    $stmtVerify->execute([$_SESSION['login_user_id'], $_SESSION['login']]);
    $user = $stmtVerify->fetch();

    if ($user) {
        // Session 数据有效且与数据库匹配
        // 更新 session 中的昵称，以防数据库中的昵称已更改
        $_SESSION['login_user_nickname'] = $user['nickname'];
        
        echo json_encode([
            'loggedIn' => true,
            'username' => $user['username'], // 使用来自数据库的确认值
            'nickname' => $user['nickname'], // 使用来自数据库的确认值
            'userId' => $user['id']          // 使用来自数据库的确认值
        ]);
    } else {
        // Session 数据过时或不匹配。清除它。
        unset($_SESSION['login_user_id']);
        unset($_SESSION['login']);
        unset($_SESSION['login_user_nickname']);
        echo json_encode(['loggedIn' => false]);
    }
} else {
    echo json_encode([
        'loggedIn' => false
    ]);
}
?>
