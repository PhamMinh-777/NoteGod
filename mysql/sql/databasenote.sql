IF DB_ID('notes_app') IS NULL CREATE DATABASE notes_app;
GO
USE notes_app;
GO

-- #####################################################################
-- #####################################################################
-- ##                                                                 ##
-- ##   SECTION A  -  LOGIN / ACCOUNTS                                ##
-- ##                                                                 ##
-- #####################################################################
-- #####################################################################

-- ---------------------------------------------------------------------
-- users
-- Registration, login, profile and the "unverified" banner.
-- email_verified_at IS NULL  => account not activated yet.
-- avatar_path IS NULL        => show the default avatar icon.
-- password_hash              => bcrypt hash only, never the password.
-- ---------------------------------------------------------------------
CREATE TABLE dbo.users (
  id                BIGINT IDENTITY(1,1) NOT NULL,
  email             NVARCHAR(255) NOT NULL,
  display_name      NVARCHAR(100) NOT NULL,
  password_hash     VARCHAR(255)  NOT NULL,
  avatar_path       NVARCHAR(500) NULL,
  email_verified_at DATETIME2(0)  NULL,
  created_at        DATETIME2(0)  NOT NULL CONSTRAINT df_users_created DEFAULT SYSUTCDATETIME(),
  CONSTRAINT pk_users PRIMARY KEY (id),
  CONSTRAINT uq_users_email UNIQUE (email)
);
GO

-- ---------------------------------------------------------------------
-- email_verifications
-- Activation link, the "link expired" page, and resend.
-- Only a SHA-256 hash of the token is stored; the raw token goes in the
-- email. Delete the row (or the whole user's rows) once it is used.
-- ---------------------------------------------------------------------
CREATE TABLE dbo.email_verifications (
  id          BIGINT IDENTITY(1,1) NOT NULL,
  user_id     BIGINT NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expires_at  DATETIME2(0) NOT NULL,
  CONSTRAINT pk_email_verifications PRIMARY KEY (id),
  CONSTRAINT uq_email_verifications_token UNIQUE (token_hash),
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id)
    REFERENCES dbo.users(id) ON DELETE CASCADE
);
CREATE INDEX idx_email_verifications_user ON dbo.email_verifications(user_id);
GO

-- ---------------------------------------------------------------------
-- password_resets
-- Reset by emailed link (token_hash) OR by typing the 6-digit OTP
-- (otp_hash). attempts limits OTP guessing; used_at marks a finished
-- reset. After a reset the user logs in manually.
-- ---------------------------------------------------------------------
CREATE TABLE dbo.password_resets (
  id          BIGINT IDENTITY(1,1) NOT NULL,
  user_id     BIGINT NOT NULL,
  otp_hash    VARCHAR(255) NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expires_at  DATETIME2(0) NOT NULL,
  used_at     DATETIME2(0) NULL,
  attempts    TINYINT NOT NULL CONSTRAINT df_password_resets_attempts DEFAULT 0,
  CONSTRAINT pk_password_resets PRIMARY KEY (id),
  CONSTRAINT uq_password_resets_token UNIQUE (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id)
    REFERENCES dbo.users(id) ON DELETE CASCADE
);
CREATE INDEX idx_password_resets_user ON dbo.password_resets(user_id);
GO

-- #####################################################################
-- #####################################################################
-- ##                                                                 ##
-- ##   SECTION B  -  NOTE APP DATA (everything except login)         ##
-- ##                                                                 ##
-- #####################################################################
-- #####################################################################

-- ---------------------------------------------------------------------
-- user_preferences  (Preferences screen)
-- One row per user. If a user has no row yet, the app uses these same
-- defaults: light theme, 16px, default note color.
-- ---------------------------------------------------------------------
CREATE TABLE dbo.user_preferences (
  user_id             BIGINT NOT NULL,
  theme               VARCHAR(10) NOT NULL CONSTRAINT df_prefs_theme DEFAULT 'light',
  font_size           TINYINT NOT NULL CONSTRAINT df_prefs_font DEFAULT 16,
  default_note_color  VARCHAR(20) NOT NULL CONSTRAINT df_prefs_color DEFAULT 'default',
  CONSTRAINT pk_user_preferences PRIMARY KEY (user_id),
  CONSTRAINT ck_prefs_theme CHECK (theme IN ('light','dark')),
  CONSTRAINT ck_prefs_font  CHECK (font_size BETWEEN 12 AND 24),
  CONSTRAINT fk_prefs_user FOREIGN KEY (user_id)
    REFERENCES dbo.users(id) ON DELETE CASCADE
);
GO

-- ---------------------------------------------------------------------
-- notes  (core note)
-- content      = HTML from the rich-text editor.
-- password_hash IS NULL => not locked. Otherwise bcrypt hash of the
--                note password.
-- Pinned notes sort by pinned_at (newest pin first).
-- ---------------------------------------------------------------------
CREATE TABLE dbo.notes (
  id             BIGINT IDENTITY(1,1) NOT NULL,
  owner_id       BIGINT NOT NULL,
  title          NVARCHAR(255) NOT NULL CONSTRAINT df_notes_title DEFAULT '',
  content        NVARCHAR(MAX) NOT NULL CONSTRAINT df_notes_content DEFAULT '',
  color          VARCHAR(20) NOT NULL CONSTRAINT df_notes_color DEFAULT 'default',
  is_pinned      BIT NOT NULL CONSTRAINT df_notes_pinned DEFAULT 0,
  pinned_at      DATETIME2(0) NULL,
  password_hash  VARCHAR(255) NULL,
  created_at     DATETIME2(0) NOT NULL CONSTRAINT df_notes_created DEFAULT SYSUTCDATETIME(),
  updated_at     DATETIME2(0) NOT NULL CONSTRAINT df_notes_updated DEFAULT SYSUTCDATETIME(),
  CONSTRAINT pk_notes PRIMARY KEY (id),
  CONSTRAINT fk_notes_owner FOREIGN KEY (owner_id)
    REFERENCES dbo.users(id) ON DELETE CASCADE
);
CREATE INDEX idx_notes_list ON dbo.notes(owner_id, is_pinned, pinned_at, updated_at);
GO

-- SQL Server has no ON UPDATE CURRENT_TIMESTAMP, so a trigger keeps
-- updated_at fresh whenever a note is saved.
CREATE TRIGGER dbo.trg_notes_updated ON dbo.notes AFTER UPDATE AS
BEGIN
  SET NOCOUNT ON;
  UPDATE n SET updated_at = SYSUTCDATETIME()
  FROM dbo.notes n JOIN inserted i ON n.id = i.id;
END;
GO

-- ---------------------------------------------------------------------
-- labels
-- A label can exist with no notes. Names are unique per user.
-- user_id has no cascade: SQL Server refuses two cascade routes from
-- users to note_labels (users->notes and users->labels). Delete a
-- user's labels before deleting the user.
-- ---------------------------------------------------------------------
CREATE TABLE dbo.labels (
  id       BIGINT IDENTITY(1,1) NOT NULL,
  user_id  BIGINT NOT NULL,
  name     NVARCHAR(100) NOT NULL,
  CONSTRAINT pk_labels PRIMARY KEY (id),
  CONSTRAINT uq_labels_user_name UNIQUE (user_id, name),
  CONSTRAINT fk_labels_user FOREIGN KEY (user_id)
    REFERENCES dbo.users(id)
);
GO

-- ---------------------------------------------------------------------
-- note_labels  (many-to-many)
-- ON DELETE CASCADE on both sides: deleting a label only removes these
-- link rows and never deletes notes.
-- ---------------------------------------------------------------------
CREATE TABLE dbo.note_labels (
  note_id   BIGINT NOT NULL,
  label_id  BIGINT NOT NULL,
  CONSTRAINT pk_note_labels PRIMARY KEY (note_id, label_id),
  CONSTRAINT fk_note_labels_note FOREIGN KEY (note_id)
    REFERENCES dbo.notes(id) ON DELETE CASCADE,
  CONSTRAINT fk_note_labels_label FOREIGN KEY (label_id)
    REFERENCES dbo.labels(id) ON DELETE CASCADE
);
CREATE INDEX idx_note_labels_label ON dbo.note_labels(label_id);
GO

-- ---------------------------------------------------------------------
-- attachments  (images, video, files)
-- The file itself lives on disk or cloud storage; this table keeps
-- details only. stored_path should be a random server-side name.
-- ---------------------------------------------------------------------
CREATE TABLE dbo.attachments (
  id             BIGINT IDENTITY(1,1) NOT NULL,
  note_id        BIGINT NOT NULL,
  original_name  NVARCHAR(255) NOT NULL,
  stored_path    NVARCHAR(500) NOT NULL,
  mime_type      VARCHAR(100) NOT NULL,
  [size]         BIGINT NOT NULL,                  -- bytes
  created_at     DATETIME2(0) NOT NULL CONSTRAINT df_attachments_created DEFAULT SYSUTCDATETIME(),
  CONSTRAINT pk_attachments PRIMARY KEY (id),
  CONSTRAINT ck_attachments_size CHECK ([size] >= 0),
  CONSTRAINT fk_attachments_note FOREIGN KEY (note_id)
    REFERENCES dbo.notes(id) ON DELETE CASCADE
);
CREATE INDEX idx_attachments_note ON dbo.attachments(note_id);
GO

-- ---------------------------------------------------------------------
-- note_shares  (Share modal + "Shared With Me")
-- shared_by + shared_at = the sharer's name and timestamp shown to the
-- recipient. Revoke = DELETE the row. A person cannot share with
-- themselves. recipient_id / shared_by have no cascade (same SQL Server
-- cascade-path rule as labels); shares still disappear when the note
-- is deleted.
-- ---------------------------------------------------------------------
CREATE TABLE dbo.note_shares (
  id            BIGINT IDENTITY(1,1) NOT NULL,
  note_id       BIGINT NOT NULL,
  recipient_id  BIGINT NOT NULL,
  shared_by     BIGINT NOT NULL,
  permission    VARCHAR(4) NOT NULL CONSTRAINT df_note_shares_perm DEFAULT 'read',
  shared_at     DATETIME2(0) NOT NULL CONSTRAINT df_note_shares_at DEFAULT SYSUTCDATETIME(),
  CONSTRAINT pk_note_shares PRIMARY KEY (id),
  CONSTRAINT uq_note_shares UNIQUE (note_id, recipient_id),
  CONSTRAINT ck_note_shares_perm CHECK (permission IN ('read','edit')),
  CONSTRAINT ck_note_shares_self CHECK (recipient_id <> shared_by),
  CONSTRAINT fk_note_shares_note FOREIGN KEY (note_id)
    REFERENCES dbo.notes(id) ON DELETE CASCADE,
  CONSTRAINT fk_note_shares_recipient FOREIGN KEY (recipient_id)
    REFERENCES dbo.users(id),
  CONSTRAINT fk_note_shares_by FOREIGN KEY (shared_by)
    REFERENCES dbo.users(id)
);
CREATE INDEX idx_note_shares_recipient ON dbo.note_shares(recipient_id, shared_at);
GO

-- ---------------------------------------------------------------------
-- note_unlock_attempts  (5-tries cooldown, enforced SERVER-side)
-- One row per (note, user). On a wrong password: failed_count += 1.
-- At 5 failures set locked_until = now + cooldown and refuse any
-- attempt while SYSUTCDATETIME() < locked_until. On success (or after
-- the cooldown ends) reset failed_count to 0 and locked_until to NULL.
-- user_id has no cascade (SQL Server cascade-path rule).
-- ---------------------------------------------------------------------
CREATE TABLE dbo.note_unlock_attempts (
  note_id       BIGINT NOT NULL,
  user_id       BIGINT NOT NULL,
  failed_count  TINYINT NOT NULL CONSTRAINT df_unlock_failed DEFAULT 0,
  locked_until  DATETIME2(0) NULL,
  CONSTRAINT pk_note_unlock_attempts PRIMARY KEY (note_id, user_id),
  CONSTRAINT fk_unlock_note FOREIGN KEY (note_id)
    REFERENCES dbo.notes(id) ON DELETE CASCADE,
  CONSTRAINT fk_unlock_user FOREIGN KEY (user_id)
    REFERENCES dbo.users(id)
);
GO

-- ---------------------------------------------------------------------
-- ai_summaries  (optional cache for the AI Summary panel)
-- One row per note. "Regenerate" = UPDATE the same row.
-- Do not summarize locked notes' content without the owner unlocking.
-- ---------------------------------------------------------------------
CREATE TABLE dbo.ai_summaries (
  note_id       BIGINT NOT NULL,
  summary       NVARCHAR(MAX) NOT NULL,
  generated_at  DATETIME2(0) NOT NULL CONSTRAINT df_ai_summaries_at DEFAULT SYSUTCDATETIME(),
  CONSTRAINT pk_ai_summaries PRIMARY KEY (note_id),
  CONSTRAINT fk_ai_summaries_note FOREIGN KEY (note_id)
    REFERENCES dbo.notes(id) ON DELETE CASCADE
);
GO

-- #####################################################################
-- CHECK: should list 11 tables (3 login + 8 note app)
-- #####################################################################
SELECT name AS table_name
FROM sys.tables
WHERE name IN ('users','email_verifications','password_resets',
               'user_preferences','notes','labels','note_labels','attachments',
               'note_shares','note_unlock_attempts','ai_summaries')
ORDER BY name;
GO
