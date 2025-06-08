<?php
// 通用PHP函数库

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
 * 读取JSON文件
 */
function readJsonFile($filePath, $defaultValue = []) {
    if (!file_exists($filePath)) {
        return $defaultValue;
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        return $defaultValue;
    }
    
    $data = json_decode($content, true);
    return $data !== null ? $data : $defaultValue;
}

/**
 * 保存JSON文件
 */
function saveJsonFile($filePath, $data) {
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonData === false) {
        return false;
    }
    
    // 确保目录存在
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    return file_put_contents($filePath, $jsonData, LOCK_EX) !== false;
}

/**
 * 获取详细保存错误信息
 */
function getDetailedSaveError($filePath, $defaultMessage = '操作失败，无法保存数据') {
    $dir = dirname($filePath);
    
    if (!is_dir($dir)) {
        return '目录不存在：' . $dir;
    }
    
    if (!is_writable($dir)) {
        return '目录不可写：' . $dir;
    }
    
    if (file_exists($filePath) && !is_writable($filePath)) {
        return '文件不可写：' . $filePath;
    }
    
    return $defaultMessage;
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
function userCanModifyTopic($topic, $currentUser) {
    // 管理员可以删除任何帖子
    if ($currentUser === 'admin') {
        return true;
    }
    
    // 作者可以删除自己的帖子
    if (isset($topic['author']) && $topic['author'] === $currentUser) {
        return true;
    }
    
    return false;
}

/**
 * 处理文件上传
 */
function handleFileUploads($inputName, $uploadType, $entityId) {
    if (!isset($_FILES[$inputName]) || empty($_FILES[$inputName]['name'][0])) {
        return [];
    }
    
    $uploadedFiles = [];
    $uploadBaseDir = 'uploads/' . $uploadType . 's/' . $entityId . '/';
    
    // 创建上传目录
    if (!file_exists($uploadBaseDir)) {
        mkdir($uploadBaseDir, 0755, true);
    }
    
    $files = $_FILES[$inputName];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        
        if ($error === UPLOAD_ERR_OK) {
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $originalName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
            
            // 验证文件大小（5MB限制）
            if ($fileSize > 5 * 1024 * 1024) {
                continue;
            }
            
            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($tmpName);
            if (!in_array($fileType, $allowedTypes)) {
                continue;
            }
            
            // 生成唯一文件名
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
            $filePath = $uploadBaseDir . $newFileName;
            
            // 移动文件
            if (move_uploaded_file($tmpName, $filePath)) {
                $uploadedFiles[] = $filePath;
            }
        }
    }
    
    return $uploadedFiles;
}

/**
 * 获取当前登录用户
 */
function getCurrentUser() {
    return isset($_SESSION['login']) ? $_SESSION['login'] : null;
}
?>
