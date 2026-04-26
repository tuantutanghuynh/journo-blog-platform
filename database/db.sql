-- ============================================================
-- DATABASE: Journo Blog
-- Generated from: doc/database_schema.md
-- ============================================================

-- Create database
CREATE DATABASE IF NOT EXISTS journo 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE journo;

-- ============================================================
-- CORE TABLES
-- ============================================================

-- 1. USERS
-- ============================================================
CREATE TABLE users (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Basic info
  name              VARCHAR(100)  NOT NULL,
  email             VARCHAR(150)  NOT NULL UNIQUE,
  password          VARCHAR(255)  NOT NULL,
  email_verified_at TIMESTAMP     NULL,

  -- Profile
  avatar            VARCHAR(500)  NULL,
  bio               TEXT          NULL,
  website           VARCHAR(300)  NULL,

  -- Authorization
  role              ENUM('user','author','admin') NOT NULL DEFAULT 'user',

  -- Timestamps
  created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- Indexes
  INDEX idx_users_email (email),
  INDEX idx_users_role  (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2. CATEGORIES
-- ============================================================
CREATE TABLE categories (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  name        VARCHAR(100)  NOT NULL UNIQUE,
  slug        VARCHAR(120)  NOT NULL UNIQUE,
  description VARCHAR(500)  NULL,
  color       VARCHAR(7)    NULL,

  -- Nested category (parent-child)
  parent_id   BIGINT UNSIGNED NULL,

  created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_categories_slug      (slug),
  INDEX idx_categories_parent_id (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3. POSTS
-- ============================================================
CREATE TABLE posts (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Relationships
  user_id       BIGINT UNSIGNED NOT NULL,
  category_id   BIGINT UNSIGNED NULL,

  -- Content
  title         VARCHAR(255) NOT NULL,
  slug          VARCHAR(300) NOT NULL UNIQUE,
  excerpt       VARCHAR(500) NULL,
  content       LONGTEXT     NOT NULL,

  -- Media
  cover_image   VARCHAR(500) NULL,

  -- Status
  status        ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  published_at  TIMESTAMP    NULL,

  -- Statistics
  view_count    INT UNSIGNED NOT NULL DEFAULT 0,

  -- SEO
  meta_title       VARCHAR(255) NULL,
  meta_description VARCHAR(500) NULL,

  -- Timestamps
  created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,

  INDEX idx_posts_user_status     (user_id, status),
  INDEX idx_posts_status_published(status, published_at),
  INDEX idx_posts_slug            (slug),
  INDEX idx_posts_category        (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 4. TAGS
-- ============================================================
CREATE TABLE tags (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  name       VARCHAR(50)  NOT NULL UNIQUE,
  slug       VARCHAR(60)  NOT NULL UNIQUE,

  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 5. POST_TAG (Pivot table - Many to Many)
-- ============================================================
CREATE TABLE post_tag (
  post_id BIGINT UNSIGNED NOT NULL,
  tag_id  BIGINT UNSIGNED NOT NULL,

  PRIMARY KEY (post_id, tag_id),

  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 6. COMMENTS (Self-referencing for nested replies)
-- ============================================================
CREATE TABLE comments (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Relationships
  post_id     BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  parent_id   BIGINT UNSIGNED NULL,

  -- Content
  body        TEXT NOT NULL,

  -- Moderation
  is_approved TINYINT(1) NOT NULL DEFAULT 1,

  created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (post_id)   REFERENCES posts(id)    ON DELETE CASCADE,
  FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,

  INDEX idx_comments_post_id   (post_id),
  INDEX idx_comments_user_id   (user_id),
  INDEX idx_comments_parent_id (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 7. MEDIA
-- ============================================================
CREATE TABLE media (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  user_id     BIGINT UNSIGNED NOT NULL,

  -- File info
  filename    VARCHAR(255) NOT NULL,
  path        VARCHAR(500) NOT NULL,
  url         VARCHAR(500) NULL,
  type        VARCHAR(50)  NOT NULL,
  size        INT UNSIGNED NOT NULL,

  created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_media_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- OPTIONAL TABLES (Extend when needed)
-- ============================================================

-- 8. LIKES (Polymorphic - for both posts and comments)
-- ============================================================
CREATE TABLE likes (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  user_id         BIGINT UNSIGNED NOT NULL,
  likeable_id     BIGINT UNSIGNED NOT NULL,
  likeable_type   VARCHAR(50)     NOT NULL,

  created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_like (user_id, likeable_id, likeable_type),

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_likes_likeable (likeable_id, likeable_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 9. FOLLOWS (User follows User)
-- ============================================================
CREATE TABLE follows (
  follower_id  BIGINT UNSIGNED NOT NULL,
  following_id BIGINT UNSIGNED NOT NULL,

  created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY  (follower_id, following_id),

  FOREIGN KEY (follower_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,

  INDEX idx_follows_following_id (following_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SEED DATA (Sample data for testing)
-- ============================================================

-- Admin user (password: 12345678)
INSERT INTO users (name, email, password, role) VALUES 
('Admin', 'admin@blog.com', '12345678', 'admin'),
('Author', 'author@blog.com', '12345678', 'author');

-- Sample categories
INSERT INTO categories (name, slug, description, color) VALUES
('Technology', 'technology', 'Tech news and articles', '#2563EB'),
('Travel', 'travel', 'Travel experiences and tips', '#22C55E'),
('Lifestyle', 'lifestyle', 'Daily life articles', '#EAB308');

-- Sample tags
INSERT INTO tags (name, slug) VALUES
('Laravel', 'laravel'),
('React', 'react'),
('JavaScript', 'javascript'),
('PHP', 'php'),
('MySQL', 'mysql');
