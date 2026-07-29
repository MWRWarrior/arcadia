-- Arcadia gallery: build notes. Run ONCE in phpMyAdmin, after schema-gallery.sql.
-- (Re-running errors with "Duplicate column name" — harmless, it just means it
--  has already been applied.)

-- Free text the author writes to explain the build: what it is for, what to
-- prioritise, what to swap. A build is a list of numbers without it.
ALTER TABLE builds
  ADD COLUMN notes TEXT NULL AFTER summary;
