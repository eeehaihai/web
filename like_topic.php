<?php
// 开启会话
session_start();

// 引入工具函数
require_once 'utils.php';

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'message' => '请求方法不允许'
    ]);
}

// 检查用户是否登录
if (!isUserLoggedIn()) {
    jsonResponse([
        'success' => false,
        'message' => '请先登录'
    ]);
}

// 获取话题ID
$topicId = isset($_POST['topicId']) ? $_POST['topicId'] : '';
$currentUser = getCurrentUser();

if (empty($topicId)) {
    jsonResponse([
        'success' => false,
        'message' => '话题ID不能为空'
    ]);
}

// 话题数据文件
$topicsFile = 'topics.json';

// 读取话题数据
$topics = readJsonFile($topicsFile, []); // 提供默认空数组
if (empty($topics) && !is_array($topics)) { // 确保即使文件存在但内容无效时，$topics也是数组
    $topics = [];
}


// 查找并更新点赞数
$found = false;
$newLikesCount = 0;
$currentUserLiked = false;

foreach ($topics as &$topic) {
    if ($topic['id'] === $topicId) {
        $found = true;
        if (!isset($topic['likedBy']) || !is_array($topic['likedBy'])) {
            $topic['likedBy'] = [];
        }

        $userIndex = array_search($currentUser, $topic['likedBy']);

        if ($userIndex !== false) {
            // 用户已点赞，取消点赞
            array_splice($topic['likedBy'], $userIndex, 1);
            $currentUserLiked = false;
        } else {
            // 用户未点赞，进行点赞
            $topic['likedBy'][] = $currentUser;
            $currentUserLiked = true;
        }
        $newLikesCount = count($topic['likedBy']);
        // $topic['likes'] = $newLikesCount; // 如果需要，可以保留一个直接的点赞计数字段，或者在读取时派生
        break;
    }
}
unset($topic); // 解除引用

if (!$found) {
    jsonResponse([
        'success' => false,
        'message' => '话题不存在'
    ]);
}

// 保存更新后的话题数据
if (saveJsonFile($topicsFile, $topics)) {
    jsonResponse([
        'success' => true,
        'message' => $currentUserLiked ? '点赞成功' : '取消点赞成功',
        'likes' => $newLikesCount,
        'isLikedByCurrentUser' => $currentUserLiked
    ]);
} else {
    jsonResponse([
        'success' => false,
        'message' => getDetailedSaveError($topicsFile, '操作失败，无法保存数据')
    ]);
}
?>
