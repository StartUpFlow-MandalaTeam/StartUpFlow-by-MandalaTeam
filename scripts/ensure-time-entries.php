<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'time_entries'");
    if ($stmt->rowCount() > 0) {
        echo "Table time_entries exists.\n";
    } else {
        echo "Table time_entries does NOT exist. Creating it now...\n";
        $pdo->exec('CREATE TABLE IF NOT EXISTS time_entries (
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
        ) ENGINE=InnoDB;');
        echo "Table time_entries created successfully.\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
