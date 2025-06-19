<?php
// 通用PHP函数库
require_once 'db.php'; // 引入数据库连接

// 定义分类数据，以便复用
$_categories = [
    'campus_life' => '校园生活',
    'study' => '学习交流',
    'secondhand' => '二手交易',
    'lost_found' => '失物招领'
];

/**
 * 获取分类名称
 */
function getCategoryName($category) {
    global $_categories;
    return isset($_categories[$category]) ? $_categories[$category] : '未知分类';
}

/**
 * 获取分类列表
 */
function getCategoryList() {
    global $_categories;
    return $_categories;
}

/**
 * JSON响应
 */
function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 检查用户是否已登录
 */
function isUserLoggedIn() {
    return isset($_SESSION['login']) && !empty($_SESSION['login']);
}

/**
 * 检查用户是否可以修改话题
 */
function userCanModifyTopic($topic_user_id, $currentUserId) {
    global $pdo;
    // 管理员可以删除任何帖子 (假设管理员用户名为 'admin' 或有特定角色标记)
    // 为了简化，我们假设 currentUserId 为 1 的是管理员
    // 或者您可以从数据库中查询用户角色
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $currentUserDetails = $stmt->fetch();

    if ($currentUserDetails && $currentUserDetails['username'] === 'admin') {
        return true;
    }
    
    // 作者可以删除自己的帖子
    if ($topic_user_id == $currentUserId) {
        return true;
    }
    
    return false;
}

/**
 * 处理文件上传并存入数据库
 */
function handleFileUploads($inputName, $uploadType, $relatedId, $entityType) {
    global $pdo; // 引入PDO对象

    if (!isset($_FILES[$inputName]) || empty($_FILES[$inputName]['name'][0])) {
        return []; // 返回空数组，表示没有文件上传或处理
    }
    
    $uploadedFilePaths = []; // 存储成功上传的文件路径（相对于项目的路径）
    
    // 根据实体类型和ID构建基础上传目录
    // 例如: uploads/topics/123/ 或 uploads/comments/456/
    $uploadBaseDir = 'uploads/' . $entityType . 's/' . $relatedId . '/';
    
    // 创建上传目录 (如果不存在)
    if (!file_exists($uploadBaseDir)) {
        if (!mkdir($uploadBaseDir, 0777, true) && !is_dir($uploadBaseDir)) {
            // 如果创建目录失败，可以记录错误或抛出异常
            // error_log("Failed to create directory: " . $uploadBaseDir);
            return []; // 返回空数组表示失败
        }
    }
    
    $files = $_FILES[$inputName];
    $fileCount = is_array($files['name']) ? count($files['name']) : ($files['name'] ? 1 : 0);
    
    for ($i = 0; $i < $fileCount; $i++) {
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        
        if ($error === UPLOAD_ERR_OK) {
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $originalName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
            
            // 验证文件大小（例如5MB限制）
            if ($fileSize > 5 * 1024 * 1024) {
                // 文件过大，跳过
                // error_log("File too large: " . $originalName);
                continue;
            }
            
            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($tmpName);
            if (!in_array($fileType, $allowedTypes)) {
                // 不允许的文件类型，跳过
                // error_log("Invalid file type: " . $originalName . " (" . $fileType . ")");
                continue;
            }
            
            // 生成唯一文件名以避免冲突，同时保留原始扩展名
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            // 清理原始文件名中的特殊字符，防止路径问题
            $safeOriginalName = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($originalName, PATHINFO_FILENAME));
            $newFileName = uniqid() . '_' . $safeOriginalName . '.' . $extension;
            $filePath = $uploadBaseDir . $newFileName; // 这是文件在服务器上的完整路径
            $relativePath = $filePath; // 对于数据库存储，通常存储相对于项目根目录的路径

            // 移动上传的文件到目标位置
            if (move_uploaded_file($tmpName, $filePath)) {
                // 文件移动成功后，将信息插入数据库
                try {
                    $stmt = $pdo->prepare("INSERT INTO images (related_id, entity_type, path) VALUES (?, ?, ?)");
                    $stmt->execute([$relatedId, $entityType, $relativePath]);
                    $uploadedFilePaths[] = $relativePath; // 将相对路径添加到结果数组
                } catch (PDOException $e) {
                    // 数据库插入失败，记录错误，可以选择删除已上传的文件
                    // error_log("Failed to insert image record into DB: " . $e->getMessage() . " for file " . $relativePath);
                    // unlink($filePath); // 可选：如果DB插入失败则删除文件
                }
            } else {
                // 文件移动失败
                // error_log("Failed to move uploaded file: " . $originalName . " to " . $filePath);
            }
        } else if ($error !== UPLOAD_ERR_NO_FILE) {
            // 处理其他上传错误 (除了没有文件上传的情况)
            // error_log("File upload error for " . (is_array($files['name']) ? $files['name'][$i] : $files['name']) . ": error code " . $error);
        }
    }
    
    return $uploadedFilePaths; // 返回存储在数据库中的文件路径数组
}


/**
 * 获取当前登录用户ID (如果需要的话，或者直接从session中获取用户名)
 */
function getCurrentUserId() {
    global $pdo;
    if (isset($_SESSION['login_user_id'])) {
        // 验证此 user_id 是否仍然有效
        $stmtVerify = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmtVerify->execute([$_SESSION['login_user_id']]);
        if ($stmtVerify->fetch()) {
            return $_SESSION['login_user_id']; // ID 有效
        } else {
            // Session 中的 User ID 失效/无效。清除 session 以强制重新登录。
            unset($_SESSION['login_user_id']);
            unset($_SESSION['login']);
            unset($_SESSION['login_user_nickname']);
            return null; // 相当于为此请求的目的注销用户
        }
    }

    // 如果 session 中只有用户名，可以通过用户名查询ID
    if (isset($_SESSION['login'])) {
        $stmt = $pdo->prepare("SELECT id, nickname FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['login']]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['login_user_id'] = $user['id']; // 为将来的调用设置它
            // 确保昵称也同步/设置
            if (isset($user['nickname'])) {
                $_SESSION['login_user_nickname'] = $user['nickname'];
            }
            return $user['id'];
        } else {
            // Session 中的用户名在数据库中未找到。清除 session。
            unset($_SESSION['login_user_id']); // 也清除 user_id
            unset($_SESSION['login']);
            unset($_SESSION['login_user_nickname']);
            return null;
        }
    }
    return null;
}

/**
 * 获取当前登录用户 (保持不变，返回用户名)
 */
function getCurrentUser() {
    return isset($_SESSION['login']) ? $_SESSION['login'] : null;
}
?>
