<?php
require_once __DIR__ . '/../includes/db.php';

try {
    echo "Checking database tables...\n";

    $tables = [
        'users' => 'CREATE TABLE IF NOT EXISTS users (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          username VARCHAR(50) NOT NULL,
          email VARCHAR(255) NOT NULL,
          avatar_path VARCHAR(255) NULL,
          password_hash VARCHAR(255) NOT NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 0,
          failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
          locked_until DATETIME NULL,
          last_login_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uk_users_username (username),
          UNIQUE KEY uk_users_email (email)
        ) ENGINE=InnoDB;',

        'time_entries' => 'CREATE TABLE IF NOT EXISTS time_entries (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          project_name VARCHAR(255) NOT NULL,
          description TEXT NULL,
          hours DECIMAL(10,4) NOT NULL,
          entry_date DATE NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_time_entries_user_date_created (user_id, entry_date, created_at),
          CONSTRAINT fk_time_entries_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ) ENGINE=InnoDB;',

        'audit_logs' => 'CREATE TABLE IF NOT EXISTS audit_logs (
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
        ) ENGINE=InnoDB;',

        'email_change_requests' => 'CREATE TABLE IF NOT EXISTS email_change_requests (
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
        ) ENGINE=InnoDB;',

        'password_resets' => 'CREATE TABLE IF NOT EXISTS password_resets (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          token_hash CHAR(64) NOT NULL,
          expires_at DATETIME NOT NULL,
          used_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uk_password_resets_token_hash (token_hash),
          KEY idx_password_resets_user_id (user_id),
          CONSTRAINT fk_password_resets_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ) ENGINE=InnoDB;',

        'email_verifications' => 'CREATE TABLE IF NOT EXISTS email_verifications (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          token_hash CHAR(64) NOT NULL,
          expires_at DATETIME NOT NULL,
          used_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uk_email_verifications_token_hash (token_hash),
          KEY idx_email_verifications_user_id (user_id),
          CONSTRAINT fk_email_verifications_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ) ENGINE=InnoDB;',

        'remember_me_tokens' => 'CREATE TABLE IF NOT EXISTS remember_me_tokens (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          selector CHAR(24) NOT NULL,
          token_hash CHAR(64) NOT NULL,
          expires_at DATETIME NOT NULL,
          used_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uk_remember_me_tokens_selector (selector),
          UNIQUE KEY uk_remember_me_tokens_token_hash (token_hash),
          KEY idx_remember_me_tokens_user_id (user_id),
          CONSTRAINT fk_remember_me_tokens_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ) ENGINE=InnoDB;',

        'users_profiles' => 'CREATE TABLE IF NOT EXISTS users_profiles (
          user_id INT UNSIGNED PRIMARY KEY,
          entity_type ENUM(\'individual\',\'company\') NOT NULL DEFAULT \'individual\',
          first_name VARCHAR(100),
          last_name VARCHAR(100),
          company_name VARCHAR(80) NULL,
          birth_date DATE,
          birth_place VARCHAR(100),
          birth_country VARCHAR(100),
          cf VARCHAR(16) UNIQUE,
          vat_country CHAR(2) NOT NULL DEFAULT \'IT\',
          vat_number VARCHAR(28) NULL,
          tax_regime CHAR(4) NULL,
          address VARCHAR(255),
          street_number VARCHAR(8) NULL,
          city VARCHAR(100),
          province CHAR(2) NULL,
          postal_code VARCHAR(10),
          country CHAR(2) NOT NULL DEFAULT \'IT\',
          phone VARCHAR(12) NULL,
          contact_email VARCHAR(256) NULL,
          professional_register VARCHAR(60) NULL,
          register_province CHAR(2) NULL,
          register_number VARCHAR(60) NULL,
          register_date DATE NULL,
          rea_office CHAR(2) NULL,
          rea_number VARCHAR(20) NULL,
          share_capital DECIMAL(14,2) NULL,
          sole_shareholder ENUM(\'SU\',\'SM\') NULL,
          liquidation_status ENUM(\'LS\',\'LN\') NULL,
          iban VARCHAR(34) NULL,
          bic VARCHAR(11) NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uk_users_profiles_vat (vat_country, vat_number),
          CONSTRAINT fk_users_profiles_user
            FOREIGN KEY (user_id)
            REFERENCES users(id)
            ON DELETE CASCADE
        ) ENGINE=InnoDB;',

        'clients' => 'CREATE TABLE IF NOT EXISTS clients (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          client_type ENUM(\'individual\',\'company\') NOT NULL,
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
          pec VARCHAR(255) NULL,
          sdi_code CHAR(7) NULL,
          crm_status ENUM(\'lead\',\'prospect\',\'customer\',\'lost\') NOT NULL DEFAULT \'lead\',
          source VARCHAR(50) NULL,
          notes TEXT NULL,
          converted_at DATETIME NULL,
          lost_reason VARCHAR(255) NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uk_clients_id_user (id, user_id),
          UNIQUE KEY uk_clients_user_vat (user_id, vat_number),
          KEY idx_clients_user_status (user_id, crm_status),
          CONSTRAINT fk_clients_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ) ENGINE=InnoDB;',

        'documents' => 'CREATE TABLE IF NOT EXISTS documents (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          client_id INT UNSIGNED NOT NULL,
          type ENUM(\'NDA\', \'Contract\') NOT NULL,
          file_path VARCHAR(255) NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          FOREIGN KEY (client_id) REFERENCES clients(id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        );',

        'invoice_counters' => 'CREATE TABLE IF NOT EXISTS invoice_counters (
            series VARCHAR(10) NOT NULL,
            year INT NOT NULL,
            last_number INT NOT NULL,
            PRIMARY KEY (series, year)
        ) ENGINE=InnoDB;',

        'client_contacts' => 'CREATE TABLE IF NOT EXISTS client_contacts (
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
        ) ENGINE=InnoDB;',

        'quotes' => 'CREATE TABLE IF NOT EXISTS quotes (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          client_id INT UNSIGNED NOT NULL,
          quote_number VARCHAR(50) NOT NULL,
          status ENUM(\'draft\',\'sent\',\'accepted\',\'rejected\',\'expired\') NOT NULL DEFAULT \'draft\',
          issue_date DATE NOT NULL,
          valid_until DATE NULL,
          currency CHAR(3) NOT NULL DEFAULT \'EUR\',
          total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          notes TEXT NULL,
          accepted_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uk_quotes_user_number (user_id, quote_number),
          KEY idx_quotes_client_user (client_id, user_id),
          CONSTRAINT fk_quotes_client_user
            FOREIGN KEY (client_id, user_id) REFERENCES clients (id, user_id)
        ) ENGINE=InnoDB;',

        'quote_items' => 'CREATE TABLE IF NOT EXISTS quote_items (
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
        ) ENGINE=InnoDB;',

        'orders' => 'CREATE TABLE IF NOT EXISTS orders (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          client_id INT UNSIGNED NOT NULL,
          quote_id INT UNSIGNED NULL,
          order_number VARCHAR(50) NOT NULL,
          status ENUM(\'draft\',\'confirmed\',\'fulfilled\',\'cancelled\') NOT NULL DEFAULT \'draft\',
          payment_status ENUM(\'unpaid\',\'partial\',\'paid\') NOT NULL DEFAULT \'unpaid\',
          currency CHAR(3) NOT NULL DEFAULT \'EUR\',
          total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          notes TEXT NULL,
          confirmed_at DATETIME NULL,
          fulfilled_at DATETIME NULL,
          paid_at DATETIME NULL,
          cancelled_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uk_orders_user_number (user_id, order_number),
          UNIQUE KEY uk_orders_quote (quote_id),
          KEY idx_orders_client_user (client_id, user_id),
          KEY idx_orders_user_status (user_id, status),
          CONSTRAINT fk_orders_client_user
            FOREIGN KEY (client_id, user_id) REFERENCES clients (id, user_id),
          CONSTRAINT fk_orders_quote
            FOREIGN KEY (quote_id) REFERENCES quotes (id)
            ON DELETE SET NULL
        ) ENGINE=InnoDB;',

        'order_items' => 'CREATE TABLE IF NOT EXISTS order_items (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          order_id INT UNSIGNED NOT NULL,
          position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
          description VARCHAR(500) NOT NULL,
          quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
          unit_price DECIMAL(10,2) NOT NULL,
          discount_type ENUM(\'none\',\'percentage\',\'amount\') NOT NULL DEFAULT \'none\',
          discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          line_total DECIMAL(10,2) NOT NULL,
          PRIMARY KEY (id),
          KEY idx_order_items_order (order_id),
          CONSTRAINT fk_order_items_order
            FOREIGN KEY (order_id) REFERENCES orders (id)
            ON DELETE CASCADE
        ) ENGINE=InnoDB;',

        'payments' => 'CREATE TABLE IF NOT EXISTS payments (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          order_id INT UNSIGNED NOT NULL,
          amount DECIMAL(10,2) NOT NULL,
          method VARCHAR(30) NULL,
          reference VARCHAR(100) NULL,
          received_at DATETIME NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_payments_order (order_id),
          CONSTRAINT fk_payments_order
            FOREIGN KEY (order_id) REFERENCES orders (id)
        ) ENGINE=InnoDB;',

        'invoices' => 'CREATE TABLE IF NOT EXISTS invoices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NOT NULL,
            document_number VARCHAR(50) NOT NULL,
            issue_date DATE NOT NULL,
            due_date DATE NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'EUR\',
            tax_regime VARCHAR(16) NOT NULL,
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            bollo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            bollo_to_client TINYINT(1) NOT NULL DEFAULT 0,
            cassa_type CHAR(4) NULL,
            cassa_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            is_paid BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_invoices_document_number (document_number),
            KEY idx_invoices_order (order_id),
            CONSTRAINT fk_invoices_client
                FOREIGN KEY (client_id)
                REFERENCES clients(id),
            CONSTRAINT fk_invoices_order
                FOREIGN KEY (order_id)
                REFERENCES orders(id)
                ON DELETE SET NULL,
            CONSTRAINT fk_invoices_user
                FOREIGN KEY (user_id)
                REFERENCES users(id)
        ) ENGINE=InnoDB;',

        'crm_activities' => 'CREATE TABLE IF NOT EXISTS crm_activities (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          client_id INT UNSIGNED NOT NULL,
          order_id INT UNSIGNED NULL,
          quote_id INT UNSIGNED NULL,
          user_id INT UNSIGNED NOT NULL,
          type ENUM(\'call\',\'email\',\'meeting\',\'note\',\'status_change\') NOT NULL,
          subject VARCHAR(255) NULL,
          body TEXT NULL,
          occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          next_action_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_act_client (client_id, occurred_at),
          KEY idx_act_next (user_id, next_action_at),
          KEY idx_act_order (order_id),
          KEY idx_act_quote (quote_id),
          CONSTRAINT fk_act_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
          CONSTRAINT fk_act_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
          CONSTRAINT fk_act_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
          CONSTRAINT fk_act_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL
        ) ENGINE=InnoDB;',

        'expenses' => 'CREATE TABLE IF NOT EXISTS expenses (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          expense_date DATE NOT NULL,
          description VARCHAR(255) NOT NULL,
          category ENUM(\'Cibo\',\'Trasporti\',\'Software\',\'Altro\') NOT NULL,
          amount DECIMAL(12,2) NOT NULL,
          receipt_path VARCHAR(255) NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_expenses_user_date (user_id, expense_date),
          CONSTRAINT fk_expenses_user FOREIGN KEY (user_id) REFERENCES users (id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    ];

    foreach ($tables as $tableName => $sql) {
        $pdo->exec($sql);
        echo "Table `{$tableName}` checked/created successfully.\n";
    }

    echo "All database tables synchronized successfully.\n";
} catch (Throwable $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}
