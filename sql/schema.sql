CREATE TABLE IF NOT EXISTS enquiries (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  phone       VARCHAR(30)  NOT NULL,
  email       VARCHAR(160) NULL,
  room        VARCHAR(120) NULL,
  checkin     DATE NULL,
  checkout    DATE NULL,
  guests      TINYINT UNSIGNED NULL,
  message     TEXT NULL,
  source      VARCHAR(40)  NOT NULL DEFAULT 'website',
  page_url    VARCHAR(255) NULL,
  referrer    VARCHAR(255) NULL,
  status      ENUM('new','contacted','booked','closed') NOT NULL DEFAULT 'new',
  notes       TEXT NULL,
  ip          VARCHAR(45)  NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_created (created_at),
  KEY idx_ip_created (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL,
  name          VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login    DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip           VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS changelog (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  version     VARCHAR(20)  NOT NULL,
  released_on DATE NOT NULL,
  type        ENUM('new','improved','fixed','security','content') NOT NULL DEFAULT 'new',
  title       VARCHAR(190) NOT NULL,
  details     TEXT NULL,
  author      VARCHAR(80) NOT NULL DEFAULT 'Developer',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_version_title (version, title),
  KEY idx_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(60) NOT NULL,
  setting_value MEDIUMTEXT NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_pages (
  route          VARCHAR(120) NOT NULL,
  title          VARCHAR(190) NULL,
  description    VARCHAR(400) NULL,
  keywords       VARCHAR(500) NULL,
  og_title       VARCHAR(190) NULL,
  og_description VARCHAR(400) NULL,
  og_image       VARCHAR(255) NULL,
  canonical_url  VARCHAR(255) NULL,
  robots         ENUM('index','noindex') NULL,
  in_sitemap     TINYINT(1) NULL,
  priority       VARCHAR(4) NULL,
  changefreq     VARCHAR(10) NULL,
  extra_schema   MEDIUMTEXT NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (route)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS blog_categories (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name             VARCHAR(80)  NOT NULL,
  slug             VARCHAR(100) NOT NULL,
  description      VARCHAR(500) NULL,
  meta_title       VARCHAR(190) NULL,
  meta_description VARCHAR(400) NULL,
  sort_order       SMALLINT NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title            VARCHAR(190) NOT NULL,
  slug             VARCHAR(190) NOT NULL,
  excerpt          VARCHAR(500) NULL,
  content          MEDIUMTEXT NULL,
  content_text     MEDIUMTEXT NULL,
  cover_image      VARCHAR(255) NULL,
  cover_alt        VARCHAR(190) NULL,
  category_id      INT UNSIGNED NULL,
  author_id        INT UNSIGNED NULL,
  author_name      VARCHAR(100) NOT NULL DEFAULT 'The Sangam House',
  status           ENUM('draft','published','trash') NOT NULL DEFAULT 'draft',
  published_at     DATETIME NULL,
  is_featured      TINYINT(1) NOT NULL DEFAULT 0,
  meta_title       VARCHAR(190) NULL,
  meta_description VARCHAR(400) NULL,
  focus_keyword    VARCHAR(120) NULL,
  meta_keywords    VARCHAR(500) NULL,
  canonical_url    VARCHAR(255) NULL,
  robots           ENUM('index','noindex') NOT NULL DEFAULT 'index',
  og_image         VARCHAR(255) NULL,
  faq              MEDIUMTEXT NULL,
  word_count       INT UNSIGNED NOT NULL DEFAULT 0,
  reading_time     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  views            INT UNSIGNED NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug),
  KEY idx_status_pub (status, published_at),
  KEY idx_category (category_id),
  KEY idx_featured (is_featured, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_tags (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_post_tags (
  post_id INT UNSIGNED NOT NULL,
  tag_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, tag_id),
  KEY idx_tag (tag_id),
  CONSTRAINT fk_bpt_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_bpt_tag  FOREIGN KEY (tag_id)  REFERENCES blog_tags (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_revisions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(190) NOT NULL,
  excerpt    VARCHAR(500) NULL,
  content    MEDIUMTEXT NULL,
  saved_by   VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_post (post_id, created_at),
  CONSTRAINT fk_brev_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_redirects (
  old_slug VARCHAR(190) NOT NULL,
  post_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (old_slug),
  KEY idx_post (post_id),
  CONSTRAINT fk_bred_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_media (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  path        VARCHAR(255) NOT NULL,
  thumb_path  VARCHAR(255) NULL,
  original    VARCHAR(190) NULL,
  alt         VARCHAR(190) NULL,
  mime        VARCHAR(40) NOT NULL,
  width       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  height      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  size_bytes  INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by VARCHAR(100) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_views_daily (
  post_id INT UNSIGNED NOT NULL,
  day     DATE NOT NULL,
  views   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (post_id, day),
  KEY idx_day (day),
  CONSTRAINT fk_bvd_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;
