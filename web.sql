-- ---------------------------------
-- 表: users (用户信息表)
-- 存储网站的所有注册用户
-- ---------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `nickname` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL, -- 注意：实际存储时应使用哈希加密（如 password_hash()）
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL UNIQUE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- 表: topics (话题表)
-- 存储所有主话题
-- ---------------------------------
CREATE TABLE `topics` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `content` TEXT NOT NULL,
  `category` ENUM('campus_life', 'study', 'secondhand', 'lost_found') NOT NULL,
  `is_anonymous` BOOLEAN NOT NULL DEFAULT FALSE,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `comments_count` INT UNSIGNED NOT NULL DEFAULT 0, -- 新增：评论计数
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE -- 用户注销后，其发布的话题也一并删除
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- 表: comments (评论表)
-- 存储对所有话题的评论以及楼中楼回复
-- ---------------------------------
CREATE TABLE `comments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `topic_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `parent_comment_id` INT UNSIGNED DEFAULT NULL, -- 用于楼中楼回复，指向父评论的ID
  `content` TEXT NOT NULL,
  `is_anonymous` BOOLEAN NOT NULL DEFAULT FALSE,
  `floor` INT UNSIGNED DEFAULT NULL, -- 仅主评论有楼层号
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reply_target_comment_id` INT UNSIGNED DEFAULT NULL,
  `reply_target_user_id` INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE, -- 话题删除时，相关评论也删除
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE, -- 用户注销时，相关评论也删除
  FOREIGN KEY (`parent_comment_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE, -- 父评论删除时，子回复也删除
  FOREIGN KEY (`reply_target_comment_id`) REFERENCES `comments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reply_target_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- 表: tags (标签表)
-- 存储所有唯一的标签，避免冗余
-- ---------------------------------
CREATE TABLE `tags` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- 表: topic_tags (话题与标签的关联表)
-- 实现话题和标签的多对多关系
-- ---------------------------------
CREATE TABLE `topic_tags` (
  `topic_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`topic_id`, `tag_id`),
  FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- 表: topic_likes (话题点赞关联表)
-- 记录用户对话题的点赞
-- ---------------------------------
CREATE TABLE `topic_likes` (
  `user_id` INT UNSIGNED NOT NULL,
  `topic_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `topic_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- 表: comment_likes (评论点赞关联表)
-- 记录用户对评论的点赞
-- ---------------------------------
CREATE TABLE `comment_likes` (
  `user_id` INT UNSIGNED NOT NULL,
  `comment_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `comment_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`comment_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- 表: images (图片存储表)
-- 统一存储话题和评论的图片路径
-- ---------------------------------
CREATE TABLE `images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `related_id` INT UNSIGNED NOT NULL, -- 关联的话题ID或评论ID
  `entity_type` ENUM('topic', 'comment') NOT NULL, -- 指明图片属于话题还是评论
  `path` VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_related_entity` (`related_id`, `entity_type`) -- 为快速查找某个话题/评论的所有图片创建索引
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;