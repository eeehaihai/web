<?php
// 开启会话
session_start();

// 引入工具函数
require_once 'utils.php';

// 统一的评论数据文件
$commentsFile = 'comments.json';
$currentUser = getCurrentUser(); // 获取当前用户以便判断点赞状态

// 发表评论
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查用户是否登录
    if (!isUserLoggedIn()) {
        jsonResponse([
            'success' => false,
            'message' => '请先登录'
        ]);
    }
    
    // 获取评论数据
    $topicId = isset($_POST['topicId']) ? $_POST['topicId'] : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $anonymous = isset($_POST['anonymous']) && $_POST['anonymous'] === '1' ? true : false; // 明确布尔值转换
    $parentCommentId = isset($_POST['parentCommentId']) ? $_POST['parentCommentId'] : '';
    $replyToFloor = isset($_POST['replyToFloor']) ? (int)$_POST['replyToFloor'] : 0;
    $replyToAuthor = isset($_POST['replyToAuthor']) ? $_POST['replyToAuthor'] : '';
    
    // 简单验证
    if (empty($topicId) || empty($content)) {
        jsonResponse([
            'success' => false,
            'message' => '话题ID和评论内容不能为空'
        ]);
    }
    
    // 读取所有评论
    $allComments = readJsonFile($commentsFile, []);
    
    // 如果没有当前话题的评论数组，创建一个
    if (!isset($allComments[$topicId]) || !is_array($allComments[$topicId])) {
        $allComments[$topicId] = [];
    }
    
    // 创建评论对象
    $commentId = uniqid();
    $author = $anonymous ? '匿名用户' : $currentUser;

    // 检查是否为楼中楼回复
    $isReply = !empty($parentCommentId);
    $uploadedImages = []; // 初始化为空数组

    if ($isReply) {
        // 楼中楼回复 - 通常不处理图片上传，或按需处理
        // $uploadedImages 保持为空，或根据需求调用 handleFileUploads
        
        $parentFound = false;
        foreach ($allComments[$topicId] as &$existingComment) {
            if ($existingComment['id'] === $parentCommentId) {
                if (!isset($existingComment['replies']) || !is_array($existingComment['replies'])) {
                    $existingComment['replies'] = [];
                }
                
                // 创建回复对象
                $reply = [
                    'id' => $commentId,
                    'content' => $content,
                    'images' => $uploadedImages, // 楼中楼回复的图片（如果支持）
                    'author' => $author,
                    'timestamp' => time() * 1000,
                    'likedBy' => [], // 初始化点赞用户列表
                    'parentCommentId' => $parentCommentId,
                    'replyToFloor' => $replyToFloor,
                    'replyToAuthor' => $replyToAuthor
                ];
                
                $existingComment['replies'][] = $reply;
                $parentFound = true;
                $commentDataForResponse = $reply; // 用于响应的数据
                break;
            }
        }
        unset($existingComment); // 解除引用
        
        if (!$parentFound) {
            jsonResponse([
                'success' => false,
                'message' => '目标评论不存在'
            ]);
        }
    } else {
        // 主评论 - 允许图片上传
        $uploadedImages = handleFileUploads('images', 'comment', $topicId . '/' . $commentId);

        $floorNumber = count($allComments[$topicId]) + 1;
        
        $newMainComment = [
            'id' => $commentId,
            'content' => $content,
            'images' => $uploadedImages, // 主评论可以有图片
            'author' => $author,
            'timestamp' => time() * 1000,
            'likedBy' => [], // 初始化点赞用户列表
            'floor' => $floorNumber,
            'replies' => []
        ];
        
        $allComments[$topicId][] = $newMainComment;
        $commentDataForResponse = $newMainComment; // 用于响应的数据
    }
    
    // 为响应数据添加初始点赞信息
    $commentDataForResponse['likes'] = 0; 
    $commentDataForResponse['currentUserLiked'] = false;

    // 保存到文件
    $saveResult = saveJsonFile($commentsFile, $allComments);
    if ($saveResult) {
        // 更新话题评论数（主评论和楼中楼回复都计入总数）
        updateTopicCommentCount($topicId, true); // 传递 true 表示增加计数
        
        jsonResponse([
            'success' => true,
            'message' => '评论发表成功',
            'comment' => $commentDataForResponse, 
            'isReply' => $isReply
        ]);
    } else {
        // 使用新的工具函数获取详细错误信息
        $errorMsg = getDetailedSaveError($commentsFile, '评论发表失败，无法保存数据');
        jsonResponse([
            'success' => false,
            'message' => $errorMsg
        ]);
    }
} 
// 获取话题的评论
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $topicId = isset($_GET['topicId']) ? $_GET['topicId'] : '';
    $sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'floor_asc';
    
    if (empty($topicId)) {
        jsonResponse([
            'success' => false,
            'message' => '话题ID不能为空'
        ]);
    }
    
    // 读取所有评论
    $allComments = readJsonFile($commentsFile, []);
    
    // 获取特定话题的评论，如果不存在则返回空数组
    $topicComments = isset($allComments[$topicId]) && is_array($allComments[$topicId]) ? $allComments[$topicId] : [];

    // 添加点赞数和当前用户点赞状态
    foreach ($topicComments as &$mainComment) {
        $mainComment['likes'] = isset($mainComment['likedBy']) ? count($mainComment['likedBy']) : 0;
        $mainComment['currentUserLiked'] = $currentUser && isset($mainComment['likedBy']) ? in_array($currentUser, $mainComment['likedBy']) : false;
        if (isset($mainComment['replies']) && is_array($mainComment['replies'])) {
            foreach ($mainComment['replies'] as &$reply) {
                $reply['likes'] = isset($reply['likedBy']) ? count($reply['likedBy']) : 0;
                $reply['currentUserLiked'] = $currentUser && isset($reply['likedBy']) ? in_array($currentUser, $reply['likedBy']) : false;
            }
            unset($reply); // 解除引用
        }
    }
    unset($mainComment); // 解除引用
    
    // 对评论进行排序
    switch ($sortBy) {
        case 'hot':
            // 按点赞数降序排序
            usort($topicComments, function($a, $b) {
                $aTotalLikes = $a['likes'] ?? 0;
                $bTotalLikes = $b['likes'] ?? 0;
                return $bTotalLikes - $aTotalLikes;
            });
            break;
        case 'floor_desc':
            // 按楼层降序排序
            usort($topicComments, function($a, $b) {
                return ($b['floor'] ?? 0) - ($a['floor'] ?? 0);
            });
            break;
        case 'floor_asc':
        default:
            // 按楼层升序排序（默认）
            usort($topicComments, function($a, $b) {
                return ($a['floor'] ?? 0) - ($b['floor'] ?? 0);
            });
            break;
    }
    
    jsonResponse([
        'success' => true,
        'comments' => $topicComments
    ]);
} else {
    jsonResponse([
        'success' => false,
        'message' => '请求方法不允许或参数错误'
    ]);
}

// 更新话题评论数
function updateTopicCommentCount($topicId, $increment = true) {
    $topicsFile = 'topics.json';
    
    $topics = readJsonFile($topicsFile, []); // 提供默认空数组
    if (empty($topics) && !is_array($topics)) {
        return;
    }
    
    $found = false;
    foreach ($topics as &$topic) {
        if ($topic['id'] === $topicId) {
            if (!isset($topic['comments'])) {
                $topic['comments'] = 0;
            }
            if ($increment) {
                $topic['comments']++;
            } else if ($topic['comments'] > 0) { // 确保不会减到负数
                $topic['comments']--;
            }
            $found = true;
            break;
        }
    }
    unset($topic); // 解除引用
    
    if ($found) {
        saveJsonFile($topicsFile, $topics);
    }
}
?>
