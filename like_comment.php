<?php
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

$topicId = isset($_POST['topicId']) ? (int)$_POST['topicId'] : 0; // topicId 在此场景下可能不是必需的，除非用于验证评论确实属于该话题
$commentId = isset($_POST['commentId']) ? (int)$_POST['commentId'] : 0;

if (empty($commentId)) {
    jsonResponse(['success' => false, 'message' => '评论ID不能为空']);
}

try {
    // 检查评论是否存在
    $stmtCheckComment = $pdo->prepare("SELECT id FROM comments WHERE id = ?");
    $stmtCheckComment->execute([$commentId]);
    if (!$stmtCheckComment->fetch()) {
        jsonResponse(['success' => false, 'message' => '评论不存在']);
    }

    // 检查用户是否已点赞该评论
    $stmtCheck = $pdo->prepare("SELECT * FROM comment_likes WHERE user_id = ? AND comment_id = ?");
    $stmtCheck->execute([$currentUserId, $commentId]);
    $existingLike = $stmtCheck->fetch();

    $currentUserLiked = false;
    if ($existingLike) {
        // 已点赞，取消点赞
        $stmtDelete = $pdo->prepare("DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?");
        $stmtDelete->execute([$currentUserId, $commentId]);
        $message = '取消点赞成功';
        $currentUserLiked = false;
    } else {
        // 未点赞，进行点赞
        $stmtInsert = $pdo->prepare("INSERT INTO comment_likes (user_id, comment_id) VALUES (?, ?)");
        $stmtInsert->execute([$currentUserId, $commentId]);
        $message = '点赞成功';
        $currentUserLiked = true;
    }

    // 获取评论最新的点赞数
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as like_count FROM comment_likes WHERE comment_id = ?");
    $stmtCount->execute([$commentId]);
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
