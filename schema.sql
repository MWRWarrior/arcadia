-- Arcadia short-link storage.
-- Run once in cPanel → phpMyAdmin, with your Arcadia database selected.

CREATE TABLE IF NOT EXISTS builds (
  id            VARCHAR(12)  NOT NULL,
  payload       TEXT         NOT NULL,           -- the encoded build ("c.…")
  payload_hash  CHAR(64)     NOT NULL,           -- sha256, so identical builds reuse an id
  created_at    DATETIME     NOT NULL,
  hits          INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_payload (payload_hash),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting only. Holds a salted hash of the address, never the address
-- itself, and rows older than an hour are deleted on the next write.
CREATE TABLE IF NOT EXISTS rate_limit (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash      CHAR(64)        NOT NULL,
  window_start DATETIME        NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ip (ip_hash),
  KEY idx_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
