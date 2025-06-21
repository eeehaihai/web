<?php
// 开启会话
session_start();

// 引入工具函数和数据库连接
require_once 'utils.php';
global $pdo;

// 检查用户是否已登录
$currentUserId = getCurrentUserId();
if (!$currentUserId) {
    jsonResponse(['success' => false, 'message' => '请先登录']);
}

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不允许']);
}

// 获取帖子ID
$topicId = isset($_POST['topicId']) ? (int)$_POST['topicId'] : 0;

if (empty($topicId)) {
    jsonResponse(['success' => false, 'message' => '帖子ID不能为空']);
}

try {
    $pdo->beginTransaction();

    // 查找话题并检查权限
    $stmtTopic = $pdo->prepare("SELECT user_id FROM topics WHERE id = ?");
    $stmtTopic->execute([$topicId]);
    $topic = $stmtTopic->fetch();

    if (!$topic) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => '帖子不存在']);
    }

    if (!userCanModifyTopic($topic['user_id'], $currentUserId)) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => '您没有权限删除此帖子']);
    }

    // 1. 删除与话题关联的图片记录和文件 (entity_type = 'topic')
    $stmtImagesTopic = $pdo->prepare("SELECT id, path FROM images WHERE related_id = ? AND entity_type = 'topic'");
    $stmtImagesTopic->execute([$topicId]);
    $topicImages = $stmtImagesTopic->fetchAll();
    foreach ($topicImages as $image) {
        if (file_exists($image['path'])) {
            unlink($image['path']);
        }
        // 可以考虑删除包含图片的目录，如果目录为空
    }
    $stmtDeleteImagesTopic = $pdo->prepare("DELETE FROM images WHERE related_id = ? AND entity_type = 'topic'");
    $stmtDeleteImagesTopic->execute([$topicId]);
    
    // 删除话题图片目录 (如果存在且为空)
    $topicImageDir = 'uploads/topics/' . $topicId . '/';
    if (is_dir($topicImageDir)) {
        // 简单的递归删除目录函数 (如果需要，可以移到 utils.php)
        function deleteDirectoryRecursive($dir) {
            if (!file_exists($dir)) return true;
            if (!is_dir($dir)) return unlink($dir);
            foreach (scandir($dir) as $item) {
                if ($item == '.' || $item == '..') continue;
                if (!deleteDirectoryRecursive($dir . DIRECTORY_SEPARATOR . $item)) return false;
            }
            return rmdir($dir);
        }
        deleteDirectoryRecursive($topicImageDir);
    }


    // 2. 删除与话题下评论关联的图片记录和文件 (entity_type = 'comment')
    // 首先获取该话题下的所有评论ID
    $stmtCommentIds = $pdo->prepare("SELECT id FROM comments WHERE topic_id = ?");
    $stmtCommentIds->execute([$topicId]);
    $commentIds = $stmtCommentIds->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($commentIds)) {
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $stmtImagesComment = $pdo->prepare("SELECT id, path FROM images WHERE related_id IN ($placeholders) AND entity_type = 'comment'");
        $stmtImagesComment->execute($commentIds);
        $commentImages = $stmtImagesComment->fetchAll();
        foreach ($commentImages as $image) {
            if (file_exists($image['path'])) {
                unlink($image['path']);
            }
            // 删除评论图片目录 (如果存在且为空)
            // 路径可能是 uploads/comments/comment_id/ 或 uploads/comments/topic_id/comment_id
            // 根据 handleFileUploads 中的目录结构来删除
            $commentImageDir = dirname($image['path']); // 获取图片所在目录
             if (is_dir($commentImageDir)) {
                // 检查目录是否为空
                $isEmpty = count(array_diff(scandir($commentImageDir), ['.', '..'])) === 0;
                if ($isEmpty) {
                    rmdir($commentImageDir);
                }
            }
        }
        $stmtDeleteImagesComment = $pdo->prepare("DELETE FROM images WHERE related_id IN ($placeholders) AND entity_type = 'comment'");
        $stmtDeleteImagesComment->execute($commentIds);
    }
     // 删除话题下所有评论的图片目录 (更粗略的方式，如果评论图片都存在于 uploads/comments/topicId/ 下)
    $commentsImageBaseDir = 'uploads/comments/' . $topicId . '/';
    if (is_dir($commentsImageBaseDir) && isset($deleteDirectoryRecursive)) { // 确保函数已定义
        $deleteDirectoryRecursive($commentsImageBaseDir);
    }


    // 3. 删除话题 (由于ON DELETE CASCADE, topic_tags, topic_likes, comments, comment_likes 会被自动删除)
    $stmtDeleteTopic = $pdo->prepare("DELETE FROM topics WHERE id = ?");
    $stmtDeleteTopic->execute([$topicId]);

    $pdo->commit();
    jsonResponse(['success' => true, 'message' => '帖子及相关数据删除成功']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['success' => false, 'message' => '删除失败: ' . $e->getMessage()]);
}
?>
