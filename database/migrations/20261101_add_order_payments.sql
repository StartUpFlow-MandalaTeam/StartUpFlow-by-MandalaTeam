-- Eseguire sul database dell'applicazione. Le verifiche evitano errori
-- se una parte della struttura è già stata creata.

SET @database_name = DATABASE();
SET @payment_status_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @database_name
      AND table_name = 'orders'
      AND column_name = 'payment_status'
);
SET @sql = IF(
    @payment_status_exists = 0,
    'ALTER TABLE orders ADD COLUMN payment_status ENUM(''unpaid'', ''partial'', ''paid'') NOT NULL DEFAULT ''unpaid''',
    'SELECT 1'
);
PREPARE migration_statement FROM @sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @paid_at_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @database_name
      AND table_name = 'orders'
      AND column_name = 'paid_at'
);
SET @sql = IF(
    @paid_at_exists = 0,
    'ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

CREATE TABLE IF NOT EXISTS payments (
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
        ON DELETE CASCADE
) ENGINE=InnoDB;
