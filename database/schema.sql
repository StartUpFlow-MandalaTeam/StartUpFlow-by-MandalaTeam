-- database/schema.sql
-- SQL schema for the login authentication system
CREATE DATABASE IF NOT EXISTS login_auth_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE login_auth_db;

-- Users table: stores account credentials and basic lockout / audit fields
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,                -- PK
  username VARCHAR(50) NOT NULL,                          -- username (unique)
  email VARCHAR(255) NOT NULL,                            -- email (unique) 
  avatar_path VARCHAR(255) NULL,                          -- relative path of the profile avatar
  password_hash VARCHAR(255) NOT NULL,                    -- hashed password (using bcrypt or Argon2)
  is_active TINYINT(1) NOT NULL DEFAULT 0,                -- account active status (0 = inactive, 1 = active)  
  failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,  -- failed attemps counter (for rate limiting)
  locked_until DATETIME NULL,                             -- if account is locked, this is the timestamp until which it remains locked
  last_login_at DATETIME NULL,                            -- timestamp of the last successful login (for audit purposes)
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,-- timestamp of account creation
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,-- timestamp of last update (for audit purposes)
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_username (username),
  UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB;

-- Audit logs: record security-relevant events and support rate-limiting checks
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  action VARCHAR(64) NOT NULL,
  meta JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action_created (action, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Email change requests: stores tokens for verifying requested email changes
CREATE TABLE IF NOT EXISTS email_change_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  new_email VARCHAR(255) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_change_user_id (user_id),
  CONSTRAINT fk_email_change_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Password resets table: stores tokens for resetting user passwords
CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,                -- PK
  user_id INT UNSIGNED NOT NULL,                          -- FK to users.id 
  token_hash CHAR(64) NOT NULL,                           -- hash of the reset token (using SHA-256)
  expires_at DATETIME NOT NULL,                           -- Token expiration timestamp  
  used_at DATETIME NULL,                                  -- Timestamp of when the token was used (null if not used yet)
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_password_resets_token_hash (token_hash),  -- Ensure token uniqueness
  KEY idx_password_resets_user_id (user_id),              -- Index for efficient lookups by user_id
  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Email verifications table: stores tokens for verifying user email addresses during registration or email change
CREATE TABLE IF NOT EXISTS email_verifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,                -- PK
  user_id INT UNSIGNED NOT NULL,                          -- FK to users.id
  token_hash CHAR(64) NOT NULL,                           -- hash of the verification token (using SHA-256)
  expires_at DATETIME NOT NULL,                           -- Token expiration timestamp
  used_at DATETIME NULL,                                  -- Timestamp of when the token was used (null if not used yet)

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_email_verifications_token_hash (token_hash),
  KEY idx_email_verifications_user_id (user_id),
  CONSTRAINT fk_email_verifications_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Remember me tokens table: stores tokens for "remember me" functionality to keep users logged in across sessions
CREATE TABLE IF NOT EXISTS remember_me_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,                -- PK
  user_id INT UNSIGNED NOT NULL,                          -- FK to users.id
  selector CHAR(24) NOT NULL,                             -- Token selector
  token_hash CHAR(64) NOT NULL,                           -- hash of the remember me token (using SHA-256)
  expires_at DATETIME NOT NULL,                           -- Token expiration timestamp  
  used_at DATETIME NULL,                                  -- Timestamp of when the token was used (null if not used yet)
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_remember_me_tokens_selector (selector),   -- Ensure selector uniqueness for efficient lookups
  UNIQUE KEY uk_remember_me_tokens_token_hash (token_hash),
  KEY idx_remember_me_tokens_user_id (user_id),
  CONSTRAINT fk_remember_me_tokens_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  birth_date DATE,
  birth_place VARCHAR(100),
  birth_country VARCHAR(100),
  cf VARCHAR(16) UNIQUE,
  address VARCHAR(255),
  city VARCHAR(100),
  postal_code VARCHAR(10),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_profiles_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE
); ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  client_type ENUM('individual', 'company') NOT NULL,
  company_name VARCHAR(255) NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  vat_number VARCHAR(20) NULL,
  cf VARCHAR(16) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(10) NULL,
  postal_code VARCHAR(10) NULL,
  country VARCHAR(100) NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(30) NULL,
  crm_status ENUM('lead', 'customer') NOT NULL DEFAULT 'lead',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clients_user_id (user_id),
  CONSTRAINT fk_clients_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    document_number VARCHAR(50) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
    tax_regime ENUM(
        'forfettario',
        'ordinario'
    ) NOT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_paid BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_invoices_document_number (document_number),
    CONSTRAINT fk_invoices_client
        FOREIGN KEY (client_id)
        REFERENCES clients(id),
    CONSTRAINT fk_invoices_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
); ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  type ENUM('NDA', 'Contract') NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  FOREIGN KEY (client_id) REFERENCES clients(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);