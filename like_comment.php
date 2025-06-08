<?php
session_start();
require_once 'utils.php';

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不允许']);
}

// 检查用户是否登录
if (!isUserLoggedIn()) {
    jsonResponse(['success' => false, 'message' => '请先登录']);
}

$topicId = isset($_POST['topicId']) ? $_POST['topicId'] : '';
$commentId = isset($_POST['commentId']) ? $_POST['commentId'] : '';
$currentUser = getCurrentUser();

if (empty($topicId) || empty($commentId)) {
    jsonResponse(['success' => false, 'message' => '话题ID和评论ID不能为空']);
}

$commentsFile = 'comments.json';
$allComments = readJsonFile($commentsFile, []);

$commentFound = false;
$newLikesCount = 0;
$currentUserLiked = false;

if (isset($allComments[$topicId])) {
    foreach ($allComments[$topicId] as &$mainComment) {
        if ($mainComment['id'] === $commentId) {
            $commentFound = true;
            if (!isset($mainComment['likedBy']) || !is_array($mainComment['likedBy'])) {
                $mainComment['likedBy'] = [];
            }
            $userIndex = array_search($currentUser, $mainComment['likedBy']);
            if ($userIndex !== false) {
                array_splice($mainComment['likedBy'], $userIndex, 1); // 取消点赞
                $currentUserLiked = false;
            } else {
                $mainComment['likedBy'][] = $currentUser; // 点赞
                $currentUserLiked = true;
            }
            $newLikesCount = count($mainComment['likedBy']);
            // $mainComment['likes'] = $newLikesCount; // 可选：更新直接的点赞计数字段
            break;
        }
        if (isset($mainComment['replies']) && is_array($mainComment['replies'])) {
            foreach ($mainComment['replies'] as &$reply) {
                if ($reply['id'] === $commentId) {
                    $commentFound = true;
                    if (!isset($reply['likedBy']) || !is_array($reply['likedBy'])) {
                        $reply['likedBy'] = [];
                    }
                    $userIndex = array_search($currentUser, $reply['likedBy']);
                    if ($userIndex !== false) {
                        array_splice($reply['likedBy'], $userIndex, 1); // 取消点赞
                        $currentUserLiked = false;
                    } else {
                        $reply['likedBy'][] = $currentUser; // 点赞
                        $currentUserLiked = true;
                    }
                    $newLikesCount = count($reply['likedBy']);
                    // $reply['likes'] = $newLikesCount; // 可选
                    break 2; // 跳出两层循环
                }
            }
            unset($reply); // 解除引用
        }
    }
    unset($mainComment); // 解除引用
}

if (!$commentFound) {
    jsonResponse(['success' => false, 'message' => '评论不存在']);
}

if (saveJsonFile($commentsFile, $allComments)) {
    jsonResponse([
        'success' => true,
        'message' => $currentUserLiked ? '点赞成功' : '取消点赞成功',
        'likes' => $newLikesCount,
        'isLikedByCurrentUser' => $currentUserLiked
    ]);
} else {
    jsonResponse(['success' => false, 'message' => getDetailedSaveError($commentsFile, '操作失败，无法保存数据')]);
}
?>
