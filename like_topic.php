<?php
// 开启会话
session_start();

// 引入工具函数和数据库连接
require_once 'utils.php';
global $pdo;

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不允许']);
}

// 检查用户是否登录
$currentUserId = getCurrentUserId();
if (!$currentUserId) {
    jsonResponse(['success' => false, 'message' => '请先登录']);
}

// 获取话题ID
$topicId = isset($_POST['topicId']) ? (int)$_POST['topicId'] : 0;

if (empty($topicId)) {
    jsonResponse(['success' => false, 'message' => '话题ID不能为空']);
}

try {
    // 检查话题是否存在
    $stmtCheckTopic = $pdo->prepare("SELECT id FROM topics WHERE id = ?");
    $stmtCheckTopic->execute([$topicId]);
    if (!$stmtCheckTopic->fetch()) {
        jsonResponse(['success' => false, 'message' => '话题不存在']);
    }

    // 检查用户是否已点赞
    $stmtCheck = $pdo->prepare("SELECT * FROM topic_likes WHERE user_id = ? AND topic_id = ?");
    $stmtCheck->execute([$currentUserId, $topicId]);
    $existingLike = $stmtCheck->fetch();

    $currentUserLiked = false;
    if ($existingLike) {
        // 已点赞，取消点赞
        $stmtDelete = $pdo->prepare("DELETE FROM topic_likes WHERE user_id = ? AND topic_id = ?");
        $stmtDelete->execute([$currentUserId, $topicId]);
        $message = '取消点赞成功';
        $currentUserLiked = false;
    } else {
        // 未点赞，进行点赞
        $stmtInsert = $pdo->prepare("INSERT INTO topic_likes (user_id, topic_id) VALUES (?, ?)");
        $stmtInsert->execute([$currentUserId, $topicId]);
        $message = '点赞成功';
        $currentUserLiked = true;
    }

    // 获取最新的点赞数
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as like_count FROM topic_likes WHERE topic_id = ?");
    $stmtCount->execute([$topicId]);
    $newLikesCount = $stmtCount->fetchColumn();

    jsonResponse([
        'success' => true,
        'message' => $message,
        'likes' => (int)$newLikesCount,
        'isLikedByCurrentUser' => $currentUserLiked
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => '操作失败: ' . $e->getMessage()]);
}
?>
