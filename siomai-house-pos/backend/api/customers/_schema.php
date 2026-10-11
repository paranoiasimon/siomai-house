<?php

if (empty($_SESSION["customers_schema_v2"])) {

/*
 * Makes sure the customers table has every column this page needs.
 * Safe to run on every request (MariaDB / XAMPP).
 */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS customers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(30) NULL,
        email VARCHAR(150) NULL,
        address VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
");

foreach ([
    "name VARCHAR(100) NOT NULL DEFAULT ''",
    "phone VARCHAR(30) NULL",
    "email VARCHAR(150) NULL",
    "address VARCHAR(255) NULL",
    "user_id INT UNSIGNED NULL",
    "client_id INT UNSIGNED NULL",
] as $definition) {
    try {
        $pdo->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS $definition");
    } catch (Throwable $e) {
        // column already fine
    }
}

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS customer_id INT UNSIGNED NULL");
} catch (Throwable $e) {
    // already there
}

    ensure_account_split_schema($pdo);

    $_SESSION["customers_schema_v2"] = true;
}
