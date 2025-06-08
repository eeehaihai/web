<?php
// 开启会话
session_start();

// 引入工具函数
require_once 'utils.php';

// 检查用户是否已登录
if (!isUserLoggedIn()) {
    jsonResponse([
        'success' => false,
        'message' => '请先登录'
    ]);
}

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'message' => '请求方法不允许'
    ]);
}

// 获取帖子ID
$topicId = isset($_POST['topicId']) ? $_POST['topicId'] : '';

if (empty($topicId)) {
    jsonResponse([
        'success' => false,
        'message' => '帖子ID不能为空'
    ]);
}

// 当前登录用户
$currentUser = getCurrentUser();

// 话题数据文件
$topicsFile = 'topics.json';
// 统一的评论数据文件
$commentsFile = 'comments.json';

// 读取话题数据
$topics = readJsonFile($topicsFile, []); // 提供默认空数组
if (empty($topics) && !is_array($topics)) { // 确保即使文件存在但内容无效时，$topics也是数组
    $topics = [];
}


// 查找话题
$topicIndex = -1;
$topicToDelete = null;
foreach ($topics as $index => $topic) {
    if ($topic['id'] === $topicId) {
        $topicIndex = $index;
        $topicToDelete = $topic;
        
        // 检查用户权限
        if (!userCanModifyTopic($topic, $currentUser)) {
            jsonResponse([
                'success' => false,
                'message' => '您没有权限删除此帖子'
            ]);
        }
        break;
    }
}

// 如果找不到帖子
if ($topicIndex === -1) {
    jsonResponse([
        'success' => false,
        'message' => '帖子不存在'
    ]);
}

// 删除帖子
array_splice($topics, $topicIndex, 1);

// 保存更新后的话题数据
if (saveJsonFile($topicsFile, $topics)) {
    // 从 comments.json 中删除与此话题相关的评论
    $allComments = readJsonFile($commentsFile, []);
    if (isset($allComments[$topicId])) {
        unset($allComments[$topicId]);
        saveJsonFile($commentsFile, $allComments); // 保存更新后的评论数据
    }
    
    // 简单的递归删除目录函数
    function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }

    // 删除话题相关的图片目录 (如果存在)
    $topicImageDir = 'uploads/topics/' . $topicId . '/';
    if (is_dir($topicImageDir)) {
        deleteDirectory($topicImageDir);
    }
    // 删除评论相关的图片目录 (如果存在)
    // 评论图片现在存储在 uploads/comments/topicId/commentId/ 结构下
    // 或者 uploads/comments/topicId/parentCommentId/replyId/
    // 这里需要更复杂的逻辑来删除所有相关的评论图片目录，或者在创建评论时就规划好目录结构以便于删除
    // 为简化，此处仅删除 uploads/comments/topicId/ 顶级目录，这可能不完全精确如果评论图片分散存储
    $commentImageBaseDir = 'uploads/comments/' . $topicId . '/';
     if (is_dir($commentImageBaseDir)) {
        deleteDirectory($commentImageBaseDir);
    }

    jsonResponse([
        'success' => true,
        'message' => '帖子删除成功'
    ]);
} else {
    jsonResponse([
        'success' => false,
        'message' => getDetailedSaveError($topicsFile, '帖子删除失败，无法保存数据')
    ]);
}
?>
