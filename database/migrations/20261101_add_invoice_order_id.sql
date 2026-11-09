SET @database_name = DATABASE();
SET @order_id_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @database_name
      AND table_name = 'invoices'
      AND column_name = 'order_id'
);
SET @sql = IF(
    @order_id_exists = 0,
    'ALTER TABLE invoices ADD COLUMN order_id INT UNSIGNED NULL AFTER client_id, ADD KEY idx_invoices_order (order_id)',
    'SELECT 1'
);
PREPARE migration_statement FROM @sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
