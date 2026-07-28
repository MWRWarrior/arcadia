-- Arcadia gallery. Run ONCE, after schema.sql, in phpMyAdmin.
-- (ALTER TABLE will error if run twice — that's harmless, it just means it's already applied.)

-- Builds are private by default. Publishing is a separate, deliberate step, so
-- share links don't silently end up in a public list.
ALTER TABLE builds
  ADD COLUMN title        VARCHAR(80)  NULL,
  ADD COLUMN author       VARCHAR(40)  NULL,
  ADD COLUMN summary      TEXT         NULL,   -- JSON: abilities + headline attributes
  ADD COLUMN votes        INT          NOT NULL DEFAULT 0,
  ADD COLUMN published    TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN published_at DATETIME     NULL,
  ADD COLUMN hidden       TINYINT(1)   NOT NULL DEFAULT 0;  -- moderation flag

ALTER TABLE builds
  ADD KEY idx_gallery (published, hidden, votes),
  ADD KEY idx_recent  (published, hidden, published_at);

-- One vote per address per build. Stores a salted hash, never the address.
CREATE TABLE IF NOT EXISTS votes (
  build_id   VARCHAR(12) NOT NULL,
  ip_hash    CHAR(64)    NOT NULL,
  created_at DATETIME    NOT NULL,
  PRIMARY KEY (build_id, ip_hash),
  KEY idx_build (build_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
