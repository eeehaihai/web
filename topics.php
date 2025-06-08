<?php
// 开启会话
session_start();

// 设置响应类型为JSON
header('Content-Type: application/json');

// 引入工具函数
require_once 'utils.php';

// 话题数据文件
$topicsFile = 'topics.json';
$currentUser = getCurrentUser(); // 获取当前用户以便判断点赞状态

// 创建话题
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查用户是否登录
    if (!isUserLoggedIn()) {
        jsonResponse([
            'success' => false,
            'message' => '请先登录'
        ]);
    }
    
    // 获取话题数据
    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
    $anonymous = isset($_POST['anonymous']) ? (bool)$_POST['anonymous'] : false;
    
    // 简单验证
    if (empty($title) || empty($category) || empty($content)) {
        jsonResponse([
            'success' => false,
            'message' => '标题、分类和内容不能为空'
        ]);
    }
    
    // 处理标签
    $cleanTags = [];
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if (!empty($tag)) {
            $cleanTags[] = $tag;
        }
    }
    
    // 创建话题对象
    $topicId = uniqid(); // 首先生成ID，以便用于图片存储路径
    $uploadedImages = handleFileUploads('images', 'topic', $topicId);

    $topic = [
        'id' => $topicId,
        'title' => $title,
        'category' => $category,
        'categoryName' => getCategoryName($category),
        'content' => $content,
        'tags' => $cleanTags,
        'images' => $uploadedImages, // 添加图片路径
        'anonymous' => $anonymous,
        'author' => $anonymous ? '匿名用户' : $currentUser,
        'timestamp' => time() * 1000, // JavaScript时间戳格式
        'views' => 0,
        'likedBy' => [], // 新增：初始化点赞用户列表
        'comments' => 0
    ];
    
    // 读取话题文件
    $topics = readJsonFile($topicsFile, []);
    
    // 添加新话题
    $topics[] = $topic;
    
    // 保存到文件
    $result = saveJsonFile($topicsFile, $topics);
    if ($result !== false) {
        jsonResponse([
            'success' => true,
            'message' => '话题发布成功',
            'topicId' => $topic['id']
        ]);
    } else {
        // 使用新的工具函数获取详细错误信息
        $errorMsg = getDetailedSaveError($topicsFile, '话题发布失败，无法保存数据');
        jsonResponse([
            'success' => false,
            'message' => $errorMsg
        ]);
    }
} 
// 获取话题列表
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['id'])) {
    $category = isset($_GET['category']) ? $_GET['category'] : 'all';
    $sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'newest';
    $timeRange = isset($_GET['timeRange']) ? $_GET['timeRange'] : '1w'; // 默认一周
    $searchTerm = isset($_GET['searchTerm']) ? strtolower(trim($_GET['searchTerm'])) : null;
    
    // 读取话题文件
    $topics = readJsonFile($topicsFile, []);
    
    // 按分类筛选
    if ($category !== 'all') {
        $filteredTopics = [];
        foreach ($topics as $topic) {
            if ($topic['category'] === $category) {
                $filteredTopics[] = $topic;
            }
        }
        $topics = $filteredTopics;
    }

    // 如果有搜索词，则进一步筛选
    if ($searchTerm !== null && $searchTerm !== '') {
        $searchedTopics = [];
        foreach ($topics as $topic) {
            $titleMatch = stripos($topic['title'], $searchTerm) !== false;
            $contentMatch = stripos($topic['content'], $searchTerm) !== false;
            $tagsMatch = false;
            if (isset($topic['tags']) && is_array($topic['tags'])) {
                foreach ($topic['tags'] as $tag) {
                    if (stripos($tag, $searchTerm) !== false) {
                        $tagsMatch = true;
                        break;
                    }
                }
            }
            if ($titleMatch || $contentMatch || $tagsMatch) {
                $searchedTopics[] = $topic;
            }
        }
        $topics = $searchedTopics;
    }

    // 添加点赞数和当前用户点赞状态
    foreach ($topics as &$topic) {
        $topic['likes'] = isset($topic['likedBy']) ? count($topic['likedBy']) : 0;
        $topic['currentUserLiked'] = $currentUser && isset($topic['likedBy']) ? in_array($currentUser, $topic['likedBy']) : false;
    }
    unset($topic); // 解除引用
    
    // 按要求排序
    if ($sortBy === 'newest') {
        // 按发布时间降序排序
        usort($topics, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
    } else if ($sortBy === 'hottest') {
        // 计算一周内的热度
        $now = time();
        $timeAgo = $now;

        switch ($timeRange) {
            case '1m': // 一月
                $timeAgo = $now - (30 * 24 * 60 * 60);
                break;
            case '6m': // 半年
                $timeAgo = $now - (6 * 30 * 24 * 60 * 60);
                break;
            case '1y': // 一年
                $timeAgo = $now - (365 * 24 * 60 * 60);
                break;
            case '1w': // 一周 (默认)
            default:
                $timeAgo = $now - (7 * 24 * 60 * 60);
                break;
        }
        $timeAgoJs = $timeAgo * 1000; // 转换为 JavaScript 时间戳
        
        // 首先筛选出在时间范围内的帖子
        $topicsInTimeRange = [];
        foreach ($topics as $topic) {
            if ($topic['timestamp'] >= $timeAgoJs) {
                $topicsInTimeRange[] = $topic;
            }
        }
        $topics = $topicsInTimeRange; // 更新$topics为筛选后的结果

        // 计算热度分数 - 基于指定时间范围内的评论数+点赞数，并移除临时字段
        // 由于已经筛选过，$topics中的所有帖子都在时间范围内
        foreach ($topics as $key => &$topic) {
            // 指定时间范围内的帖子，热度 = 评论数 + 点赞数
            $topic['hotScore'] = ($topic['comments'] ?? 0) + ($topic['likes'] ?? 0);
        }
        unset($topic); // 解除引用
        
        // 按热度分数降序排序
        usort($topics, function($a, $b) {
            return ($b['hotScore'] ?? 0) - ($a['hotScore'] ?? 0);
        });
        
        // 移除临时字段
        foreach ($topics as &$topic) {
            unset($topic['hotScore']);
        }
        unset($topic); // 解除引用
    } 
    
    jsonResponse([
        'success' => true,
        'topics' => $topics
    ]);
}
// 获取单个话题详情
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $topicId = $_GET['id'];
    
    // 读取话题文件
    $topicsData = readJsonFile($topicsFile); 
    if (empty($topicsData)) {
        jsonResponse([
            'success' => false,
            'message' => '话题数据文件不存在或为空'
        ]);
    }
    
    // 查找指定话题
    $topic = null;
    $topicIndex = -1;
    foreach ($topicsData as $index => &$t) {
        if ($t['id'] === $topicId) {
            // 增加浏览量
            $t['views'] = ($t['views'] ?? 0) + 1;
            $topic = $t;
            $topicIndex = $index;
            break;
        }
    }
    unset($t); // 解除引用
    
    // 保存更新后的话题列表（浏览量增加）
    if ($topic) {
        saveJsonFile($topicsFile, $topicsData);
        
        // 为单个话题添加点赞数和当前用户点赞状态
        $topic['likes'] = isset($topic['likedBy']) ? count($topic['likedBy']) : 0;
        $topic['currentUserLiked'] = $currentUser && isset($topic['likedBy']) ? in_array($currentUser, $topic['likedBy']) : false;

        jsonResponse([
            'success' => true,
            'topic' => $topic
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => '话题不存在'
        ]);
    }
} else {
    jsonResponse([
        'success' => false,
        'message' => '请求方法不允许或参数错误'
    ]);
}
?>
