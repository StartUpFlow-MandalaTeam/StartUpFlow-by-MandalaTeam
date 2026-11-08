SET @database_name = DATABASE();

SET @discount_type_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @database_name
      AND table_name = 'order_items'
      AND column_name = 'discount_type'
);
SET @sql = IF(
    @discount_type_exists = 0,
    'ALTER TABLE order_items
        ADD COLUMN discount_type ENUM(''none'',''percentage'',''amount'') NOT NULL DEFAULT ''none'' AFTER unit_price,
        ADD COLUMN discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_type,
        ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_value',
    'SELECT 1'
);
PREPARE migration_statement FROM @sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
