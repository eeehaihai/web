<?php
// 开启会话
session_start();

// 引入工具函数和数据库连接
require_once 'utils.php';
global $pdo;

$currentUser = getCurrentUser(); // 获取当前登录用户名
$currentUserId = getCurrentUserId(); // 获取当前登录用户ID

// 发表评论
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isUserLoggedIn() || !$currentUserId) {
        jsonResponse(['success' => false, 'message' => '请先登录']);
    }
    
    $topicId = isset($_POST['topicId']) ? (int)$_POST['topicId'] : 0;
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $is_anonymous = isset($_POST['anonymous']) && $_POST['anonymous'] == '1' ? 1 : 0;
    $parentCommentId = isset($_POST['parentCommentId']) && !empty($_POST['parentCommentId']) ? (int)$_POST['parentCommentId'] : null;
    $replyTargetCommentId = isset($_POST['replyTargetCommentId']) && !empty($_POST['replyTargetCommentId']) ? (int)$_POST['replyTargetCommentId'] : null;
    $replyTargetUserId = isset($_POST['replyTargetUserId']) && !empty($_POST['replyTargetUserId']) ? (int)$_POST['replyTargetUserId'] : null;

    if (empty($topicId) || empty($content)) {
        jsonResponse(['success' => false, 'message' => '话题ID和评论内容不能为空']);
    }

    try {
        $pdo->beginTransaction();

        // 确定楼层号 (仅主评论)
        $floorNumber = null;
        if (!$parentCommentId) {
            $stmtFloor = $pdo->prepare("SELECT MAX(floor) as max_floor FROM comments WHERE topic_id = ? AND parent_comment_id IS NULL");
            $stmtFloor->execute([$topicId]);
            $maxFloor = $stmtFloor->fetchColumn();
            $floorNumber = $maxFloor ? $maxFloor + 1 : 1;
        }

        // 修改插入语句，增加两个新字段
        $stmt = $pdo->prepare("INSERT INTO comments (topic_id, user_id, parent_comment_id, content, is_anonymous, floor, reply_target_comment_id, reply_target_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$topicId, $currentUserId, $parentCommentId, $content, $is_anonymous, $floorNumber, $replyTargetCommentId, $replyTargetUserId]);
        $commentId = $pdo->lastInsertId();

        // 处理图片上传 (entityType 为 'comment')
        // 图片路径将与 commentId 关联
        $uploadedImages = handleFileUploads('images', 'comment', $commentId, 'comment');


        // 更新话题的评论数
        $stmtUpdateTopic = $pdo->prepare("UPDATE topics SET comments_count = comments_count + 1 WHERE id = ?");
        $stmtUpdateTopic->execute([$topicId]);

        $pdo->commit();

        // 获取刚插入的评论的完整信息以返回给前端
        $stmtFetchComment = $pdo->prepare(
            "SELECT c.id, c.content, c.is_anonymous, c.floor, c.created_at, c.parent_comment_id,
                    IF(c.is_anonymous, '匿名用户', u.nickname) as author,
                    (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) as likes,
                    0 as currentUserLiked -- 新评论，当前用户肯定没点赞
             FROM comments c 
             JOIN users u ON c.user_id = u.id 
             WHERE c.id = ?"
        );
        $stmtFetchComment->execute([$commentId]);
        $newCommentData = $stmtFetchComment->fetch();
        
        if ($newCommentData) {
            $newCommentData['timestamp'] = strtotime($newCommentData['created_at']) * 1000;
            $newCommentData['images'] = $uploadedImages; // 添加图片信息
             // 如果是回复，需要获取被回复者的信息
            if ($newCommentData['parent_comment_id']) {
                $stmtParent = $pdo->prepare(
                    "SELECT comm.floor as replyToFloor, IF(comm.is_anonymous, '匿名用户', usr.nickname) as replyToAuthor 
                     FROM comments comm JOIN users usr ON comm.user_id = usr.id 
                     WHERE comm.id = (SELECT c_parent.parent_comment_id FROM comments c_parent WHERE c_parent.id = ?)
                     LIMIT 1"
                );
                $stmtParent->execute([$newCommentData['parent_comment_id']]);
                $parentData = $stmtParent->fetch();

                if ($parentData) {
                    $newCommentData['replyToFloor'] = $parentData['replyToFloor'];
                    $newCommentData['replyToAuthor'] = $parentData['replyToAuthor'];
                } else {
                    $newCommentData['replyToFloor'] = null;
                    $newCommentData['replyToAuthor'] = null;
                }
            } else {
                $newCommentData['replyToFloor'] = null;
                $newCommentData['replyToAuthor'] = null;
            }
        }


        jsonResponse([
            'success' => true,
            'message' => '评论发表成功',
            'comment' => $newCommentData,
            'isReply' => (bool)$parentCommentId
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => '评论发表失败: ' . $e->getMessage()]);
    }
}
// 获取话题的评论
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $topicId = isset($_GET['topicId']) ? (int)$_GET['topicId'] : 0;
    $sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'floor_asc'; // 'floor_asc', 'floor_desc', 'hot'
    
    if (empty($topicId)) {
        jsonResponse(['success' => false, 'message' => '话题ID不能为空']);
    }

    try {
        // 获取主评论
        $sqlMain = "SELECT c.id, c.user_id as author_user_id, c.content, c.is_anonymous, c.floor, c.created_at,
                           IF(c.is_anonymous, '匿名用户', u.nickname) as author,
                           (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) as likes";
        if ($currentUserId) {
            $sqlMain .= ", (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id AND cl.user_id = :currentUserIdMain) > 0 as currentUserLiked";
        } else {
            $sqlMain .= ", 0 as currentUserLiked";
        }
        $sqlMain .= " FROM comments c JOIN users u ON c.user_id = u.id 
                      WHERE c.topic_id = :topicId AND c.parent_comment_id IS NULL";

        if ($sortBy === 'hot') {
            $sqlMain .= " ORDER BY likes DESC, c.created_at ASC"; // 按点赞数降序，然后按时间升序
        } elseif ($sortBy === 'floor_desc') {
            $sqlMain .= " ORDER BY c.floor DESC";
        } else { // floor_asc or default
            $sqlMain .= " ORDER BY c.floor ASC";
        }
        
        $stmtMain = $pdo->prepare($sqlMain);
        $mainParams = [':topicId' => $topicId];
        if ($currentUserId) $mainParams[':currentUserIdMain'] = $currentUserId;
        $stmtMain->execute($mainParams);
        $comments = $stmtMain->fetchAll();

        foreach ($comments as &$comment) {
            $comment['timestamp'] = strtotime($comment['created_at']) * 1000;
            $comment['likes'] = (int)$comment['likes'];
            $comment['currentUserLiked'] = (bool)$comment['currentUserLiked'];

            // 获取主评论的图片
            $stmtImagesMain = $pdo->prepare("SELECT path FROM images WHERE related_id = ? AND entity_type = 'comment'");
            $stmtImagesMain->execute([$comment['id']]);
            $comment['images'] = $stmtImagesMain->fetchAll(PDO::FETCH_COLUMN);

            // 获取楼中楼回复
            $sqlReplies = "SELECT r.id, r.user_id as author_user_id, r.content, r.is_anonymous, r.created_at, r.parent_comment_id,
                                  IF(r.is_anonymous, '匿名用户', ur.nickname) as author, /* 当前这条回复r的作者 */
                                  
                                  /* 关于被回复的评论(target_comment)的信息 */
                                  /* replyToFloor 应该始终是主评论的楼层号 */
                                  (SELECT pc.floor FROM comments pc WHERE pc.id = r.parent_comment_id) as replyToFloor,
                                  (SELECT IF(tc.is_anonymous, '匿名用户', tu.nickname) 
                                   FROM comments tc 
                                   JOIN users tu ON tc.user_id = tu.id 
                                   WHERE tc.id = r.reply_target_comment_id
                                  ) as replyToAuthor, /* 被回复的评论的作者昵称（考虑其匿名状态） */
                                  
                                  (SELECT COUNT(*) FROM comment_likes cl_r WHERE cl_r.comment_id = r.id) as likes";
            if ($currentUserId) {
                $sqlReplies .= ", (SELECT COUNT(*) FROM comment_likes cl_r WHERE cl_r.comment_id = r.id AND cl_r.user_id = :currentUserIdReply) > 0 as currentUserLiked";
            } else {
                $sqlReplies .= ", 0 as currentUserLiked";
            }
            $sqlReplies .= " FROM comments r JOIN users ur ON r.user_id = ur.id /* ur 是这条回复r的作者信息 */
                             WHERE r.topic_id = :topicIdReply AND r.parent_comment_id = :parentCommentId
                             ORDER BY r.created_at ASC"; // 楼中楼按时间升序

            $stmtReplies = $pdo->prepare($sqlReplies);
            $replyParams = [':topicIdReply' => $topicId, ':parentCommentId' => $comment['id']];
            if ($currentUserId) $replyParams[':currentUserIdReply'] = $currentUserId;

            $stmtReplies->execute($replyParams);
            $replies = $stmtReplies->fetchAll();

            foreach ($replies as &$reply) {
                $reply['timestamp'] = strtotime($reply['created_at']) * 1000;
                $reply['likes'] = (int)$reply['likes'];
                $reply['currentUserLiked'] = (bool)$reply['currentUserLiked'];
                // replyToFloor 和 replyToAuthor 现在直接从查询中获取，基于 reply_target_comment_id 和 reply_target_user_id
                // $reply['replyToFloor'] = $comment['floor']; // 旧逻辑，已由SQL查询替代
                // $reply['replyToAuthor'] = $comment['author']; // 旧逻辑，已由SQL查询替代

                // 获取楼中楼回复的图片
                $stmtImagesReply = $pdo->prepare("SELECT path FROM images WHERE related_id = ? AND entity_type = 'comment'");
                $stmtImagesReply->execute([$reply['id']]);
                $reply['images'] = $stmtImagesReply->fetchAll(PDO::FETCH_COLUMN);
            }
            unset($reply);
            $comment['replies'] = $replies;
        }
        unset($comment);

        jsonResponse(['success' => true, 'comments' => $comments]);

    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => '获取评论失败: ' . $e->getMessage()]);
    }

} else {
    jsonResponse(['success' => false, 'message' => '请求方法不允许或参数错误']);
}

// updateTopicCommentCount 函数不再需要，因为我们在创建评论时直接更新
?>
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
