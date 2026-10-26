-- database/schema.sql
-- SQL schema for the login authentication system
CREATE DATABASE IF NOT EXISTS login_auth_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE login_auth_db;

-- =====================================================================
-- AUTENTICAZIONE
-- =====================================================================

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
) ENGINE=InnoDB;

-- =====================================================================
-- CRM CLIENTI
-- =====================================================================

-- Anagrafica unica: lead, prospect, customer e lost
-- Il cambio di stato sposta solo crm_status: nessun dato viene copiato tra tabelle.
-- crm_status e converted_at vanno scritti SOLO da ClientService::markAsCustomer()
-- (e dagli altri metodi del servizio per le transizioni manuali).
CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,                          -- proprietario (chi emette fatture)
  client_type ENUM('individual','company') NOT NULL,
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
  pec VARCHAR(255) NULL,                                  -- fatturazione elettronica
  sdi_code CHAR(7) NULL,                                  -- codice destinatario SDI
  -- campi CRM
  crm_status ENUM('lead','prospect','customer','lost') NOT NULL DEFAULT 'lead',
  source VARCHAR(50) NULL,                                -- sito, referral, fiera...
  notes TEXT NULL,
  converted_at DATETIME NULL,                             -- data dell'ultima promozione a customer (primo ordine confermato)
  lost_reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_clients_id_user (id, user_id),            -- necessaria per le FK composte (quotes, orders, invoices)
  UNIQUE KEY uk_clients_user_vat (user_id, vat_number),   -- anti-duplicati (i NULL sono ammessi)
  KEY idx_clients_user_status (user_id, crm_status),
  CONSTRAINT fk_clients_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

-- Persone di riferimento di un cliente/azienda
CREATE TABLE IF NOT EXISTS client_contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  role VARCHAR(100) NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(30) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contacts_client (client_id),
  CONSTRAINT fk_contacts_client
    FOREIGN KEY (client_id) REFERENCES clients (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- FLUSSO COMMERCIALE: preventivo -> ordine -> evasione -> pagamento
-- =====================================================================
-- Preventivi
CREATE TABLE IF NOT EXISTS quotes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  quote_number VARCHAR(50) NOT NULL,
  status ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  issue_date DATE NOT NULL,
  valid_until DATE NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,              -- derivato: calcolato dal servizio
  notes TEXT NULL,
  accepted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_quotes_user_number (user_id, quote_number),
  KEY idx_quotes_client_user (client_id, user_id),
  CONSTRAINT fk_quotes_client_user
    FOREIGN KEY (client_id, user_id) REFERENCES clients (id, user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quote_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote_id INT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  description VARCHAR(500) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(10,2) NOT NULL,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_quote_items_quote (quote_id),
  CONSTRAINT fk_quote_items_quote
    FOREIGN KEY (quote_id) REFERENCES quotes (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ordini
-- La conferma è inserita manualmente: status='confirmed' + confirmed_at.
-- È l'evento che fa scattare ClientService::markAsCustomer().
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  quote_id INT UNSIGNED NULL,                             -- NULL se l'ordine nasce senza preventivo
  order_number VARCHAR(50) NOT NULL,
  status ENUM('draft','confirmed','fulfilled','cancelled') NOT NULL DEFAULT 'draft',
  payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,              -- derivato: calcolato dal servizio, non modificare dopo la conferma
  notes TEXT NULL,
  confirmed_at DATETIME NULL,
  fulfilled_at DATETIME NULL,
  paid_at DATETIME NULL,                                  -- saldo completo
  cancelled_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_orders_user_number (user_id, order_number),
  UNIQUE KEY uk_orders_quote (quote_id),                  -- un preventivo genera al massimo un ordine
  KEY idx_orders_client_user (client_id, user_id),
  KEY idx_orders_user_status (user_id, status),
  CONSTRAINT fk_orders_client_user
    FOREIGN KEY (client_id, user_id) REFERENCES clients (id, user_id),
  CONSTRAINT fk_orders_quote
    FOREIGN KEY (quote_id) REFERENCES quotes (id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  description VARCHAR(500) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(10,2) NOT NULL,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id),
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pagamenti (più rate/acconti per ordine).
-- orders.payment_status e paid_at si aggiornano dal servizio, nella stessa
-- transazione dell'inserimento del pagamento (somma pagamenti vs orders.total).
CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method VARCHAR(30) NULL,                                -- bonifico, carta, contanti...
  reference VARCHAR(100) NULL,                            -- CRO, id transazione
  received_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payments_order (order_id),
  CONSTRAINT fk_payments_order
    FOREIGN KEY (order_id) REFERENCES orders (id)
) ENGINE=InnoDB;

-- =====================================================================
-- STORICO CRM
-- =====================================================================

-- Timeline: chiamate, email, meeting, note, cambi di stato, prossime azioni.
-- order_id / quote_id collegano l'evento al documento che l'ha causato
-- (es. la promozione a customer punta all'ordine confermato).
CREATE TABLE IF NOT EXISTS crm_activities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NULL,
  quote_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('call','email','meeting','note','status_change') NOT NULL,
  subject VARCHAR(255) NULL,
  body TEXT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  next_action_at DATETIME NULL,                           -- promemoria follow-up
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_act_client (client_id, occurred_at),
  KEY idx_act_next (user_id, next_action_at),
  KEY idx_act_order (order_id),
  KEY idx_act_quote (quote_id),
  CONSTRAINT fk_act_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
  CONSTRAINT fk_act_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
  CONSTRAINT fk_act_order  FOREIGN KEY (order_id)  REFERENCES orders (id)  ON DELETE SET NULL,
  CONSTRAINT fk_act_quote  FOREIGN KEY (quote_id)  REFERENCES quotes (id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- RIFERIMENTO: promozione a customer (da eseguire nel servizio, in transazione)
-- =====================================================================
-- Chiamata alla conferma manuale dell'ordine. Idempotente: se il cliente è già
-- 'customer' non tocca nulla (affected rows = 0).
--
--   UPDATE clients
--      SET crm_status = 'customer',
--          converted_at = NOW(),
--          lost_reason = NULL
--    WHERE id = :client_id AND user_id = :user_id
--      AND crm_status IN ('lead','prospect','lost');
--
-- Se affected rows = 1, nella STESSA transazione:
--
--   INSERT INTO crm_activities (client_id, order_id, user_id, type, subject, body)
--   VALUES (:client_id, :order_id, :user_id, 'status_change',
--           'Promosso a customer', 'Da <stato precedente> a customer (ordine <order_number>)');
--
-- Transizioni ammesse: lead->prospect, lead->customer, prospect->customer,
-- lead|prospect->lost, lost->customer (solo via ordine confermato).
-- L'annullamento di un ordine NON riporta indietro lo stato del cliente.