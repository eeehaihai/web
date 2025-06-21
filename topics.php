<?php
// 开启会话
session_start();

// 设置响应类型为JSON
header('Content-Type: application/json');

// 引入工具函数和数据库连接
require_once 'utils.php'; // utils.php 现在也 require 'db.php'
global $pdo;

$currentUser = getCurrentUser(); // 获取当前登录用户名
$currentUserId = getCurrentUserId(); // 获取当前登录用户ID

// 创建话题
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isUserLoggedIn() || !$currentUserId) {
        jsonResponse(['success' => false, 'message' => '请先登录']);
    }
    
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $tagsInput = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
    $is_anonymous = isset($_POST['anonymous']) && $_POST['anonymous'] == '1' ? 1 : 0;

    if (empty($title) || empty($category) || empty($content)) {
        jsonResponse(['success' => false, 'message' => '标题、分类和内容不能为空']);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO topics (user_id, title, content, category, is_anonymous) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$currentUserId, $title, $content, $category, $is_anonymous]);
        $topicId = $pdo->lastInsertId();

        // 处理标签
        $cleanTags = [];
        foreach ($tagsInput as $tag) {
            $tagName = trim($tag);
            if (!empty($tagName)) {
                // 检查标签是否存在，不存在则插入
                $stmtTag = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                $stmtTag->execute([$tagName]);
                $tagRow = $stmtTag->fetch();
                if ($tagRow) {
                    $tagId = $tagRow['id'];
                } else {
                    $stmtInsertTag = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                    $stmtInsertTag->execute([$tagName]);
                    $tagId = $pdo->lastInsertId();
                }
                // 关联话题与标签
                $stmtTopicTag = $pdo->prepare("INSERT INTO topic_tags (topic_id, tag_id) VALUES (?, ?)");
                $stmtTopicTag->execute([$topicId, $tagId]);
                $cleanTags[] = $tagName; // 用于响应
            }
        }
        
        // 处理图片上传，现在 handleFileUploads 会将图片信息存入数据库
        // entityType 为 'topic'
        $uploadedImages = handleFileUploads('images', 'topic', $topicId, 'topic');

        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => '话题发布成功',
            'topicId' => $topicId,
            // 可以选择返回完整的话题数据，但通常ID就够了，前端可以重新请求详情
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => '话题发布失败: ' . $e->getMessage()]);
    }
}
// 获取话题列表
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['id'])) {
    $categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'all';
    $sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'newest';
    $timeRange = isset($_GET['timeRange']) ? $_GET['timeRange'] : '1w';
    $searchTerm = isset($_GET['searchTerm']) ? trim($_GET['searchTerm']) : null;

    $sql = "SELECT t.id, t.user_id, t.title, t.content, t.category, t.is_anonymous, t.views, t.comments_count, t.created_at, t.updated_at, 
                   IF(t.is_anonymous, '匿名用户', u.username) as author_username, -- 使用 username
                   (SELECT COUNT(*) FROM topic_likes tl WHERE tl.topic_id = t.id) as likes_count";
    if ($currentUserId) {
        $sql .= ", (SELECT COUNT(*) FROM topic_likes tl WHERE tl.topic_id = t.id AND tl.user_id = :currentUserId) > 0 as currentUserLiked";
    } else {
        $sql .= ", 0 as currentUserLiked"; // 未登录用户默认未点赞
    }
    $sql .= " FROM topics t JOIN users u ON t.user_id = u.id";
    
    $whereClauses = [];
    $params = [];

    if ($currentUserId) {
        $params[':currentUserId'] = $currentUserId;
    }

    if ($categoryFilter !== 'all') {
        $whereClauses[] = "t.category = :category";
        $params[':category'] = $categoryFilter;
    }

    if ($searchTerm) {
        $whereClauses[] = "(t.title LIKE :searchTerm OR t.content LIKE :searchTerm OR EXISTS (SELECT 1 FROM topic_tags tt JOIN tags ta ON tt.tag_id = ta.id WHERE tt.topic_id = t.id AND ta.name LIKE :searchTermTag))";
        $params[':searchTerm'] = "%$searchTerm%";
        $params[':searchTermTag'] = "%$searchTerm%";
    }
    
    if ($sortBy === 'hottest') {
        $timeCondition = "t.created_at >= DATE_SUB(NOW(), INTERVAL ";
        switch ($timeRange) {
            case '1m': $timeCondition .= "1 MONTH)"; break;
            case '6m': $timeCondition .= "6 MONTH)"; break;
            case '1y': $timeCondition .= "1 YEAR)"; break;
            case '1w': default: $timeCondition .= "7 DAY)"; break;
        }
        $whereClauses[] = $timeCondition;
    }

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    if ($sortBy === 'newest') {
        $sql .= " ORDER BY t.created_at DESC";
    } else if ($sortBy === 'hottest') {
        // 热度计算：(views / 10) + likes_count + (comments_count * 2)
        // 这里的 likes_count 是子查询，comments_count 是表字段
        $sql .= " ORDER BY (t.views / 10 + (SELECT COUNT(*) FROM topic_likes tl WHERE tl.topic_id = t.id) + (t.comments_count * 2)) DESC, t.created_at DESC";
    }
    // 可以添加分页逻辑 LIMIT :offset, :limit

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $topics = $stmt->fetchAll();

        foreach ($topics as &$topic) {
            $topic['categoryName'] = getCategoryName($topic['category']);
            $topic['timestamp'] = strtotime($topic['created_at']) * 1000; // 转为JS时间戳
            $topic['author'] = $topic['author_username']; // 统一字段名，确保这里是 username
            $topic['likes'] = (int)$topic['likes_count'];
            $topic['comments'] = (int)$topic['comments_count'];
            $topic['currentUserLiked'] = (bool)$topic['currentUserLiked'];

            // 获取话题的标签
            $stmtTags = $pdo->prepare("SELECT ta.name FROM tags ta JOIN topic_tags tt ON ta.id = tt.tag_id WHERE tt.topic_id = ?");
            $stmtTags->execute([$topic['id']]);
            $topic['tags'] = $stmtTags->fetchAll(PDO::FETCH_COLUMN);

            // 获取话题的图片
            $stmtImages = $pdo->prepare("SELECT path FROM images WHERE related_id = ? AND entity_type = 'topic'");
            $stmtImages->execute([$topic['id']]);
            $topic['images'] = $stmtImages->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($topic);

        jsonResponse(['success' => true, 'topics' => $topics]);

    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => '获取话题列表失败: ' . $e->getMessage()]);
    }
}
// 获取单个话题详情
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $topicId = $_GET['id'];

    try {
        // 增加浏览量
        $stmtUpdateViews = $pdo->prepare("UPDATE topics SET views = views + 1 WHERE id = ?");
        $stmtUpdateViews->execute([$topicId]);

        $sql = "SELECT t.id, t.user_id, t.title, t.content, t.category, t.is_anonymous, t.views, t.comments_count, t.created_at,
                       IF(t.is_anonymous, '匿名用户', u.username) as author_username, -- 使用 username
                       (SELECT COUNT(*) FROM topic_likes tl WHERE tl.topic_id = t.id) as likes_count";
        if ($currentUserId) {
            $sql .= ", (SELECT COUNT(*) FROM topic_likes tl WHERE tl.topic_id = t.id AND tl.user_id = :currentUserId) > 0 as currentUserLiked";
        } else {
            $sql .= ", 0 as currentUserLiked";
        }
        $sql .= " FROM topics t JOIN users u ON t.user_id = u.id WHERE t.id = :topicId";
        
        $stmt = $pdo->prepare($sql);
        $params = [':topicId' => $topicId];
        if ($currentUserId) {
            $params[':currentUserId'] = $currentUserId;
        }
        $stmt->execute($params);
        $topic = $stmt->fetch();

        if ($topic) {
            $topic['categoryName'] = getCategoryName($topic['category']);
            $topic['timestamp'] = strtotime($topic['created_at']) * 1000;
            $topic['author'] = $topic['author_username']; // 确保这里是 username
            $topic['likes'] = (int)$topic['likes_count'];
            $topic['comments'] = (int)$topic['comments_count'];
            $topic['currentUserLiked'] = (bool)$topic['currentUserLiked'];

            // 获取标签
            $stmtTags = $pdo->prepare("SELECT ta.name FROM tags ta JOIN topic_tags tt ON ta.id = tt.tag_id WHERE tt.topic_id = ?");
            $stmtTags->execute([$topicId]);
            $topic['tags'] = $stmtTags->fetchAll(PDO::FETCH_COLUMN);

            // 获取图片
            $stmtImages = $pdo->prepare("SELECT path FROM images WHERE related_id = ? AND entity_type = 'topic'");
            $stmtImages->execute([$topicId]);
            $topic['images'] = $stmtImages->fetchAll(PDO::FETCH_COLUMN);
            
            jsonResponse(['success' => true, 'topic' => $topic]);
        } else {
            jsonResponse(['success' => false, 'message' => '话题不存在']);
        }
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => '获取话题详情失败: ' . $e->getMessage()]);
    }
} else {
    jsonResponse(['success' => false, 'message' => '请求方法不允许或参数错误']);
}
?>
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
