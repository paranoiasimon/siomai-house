<?php
/*
 * ONE-TIME fix so products can also be deleted directly in phpMyAdmin.
 *
 * Open:  http://localhost/siomai-house-pos/fix-product-delete.php
 * Works from localhost only. Safe to run again. Delete it when finished.
 *
 * What it does: sold items keep the product NAME, and the links from
 * order_items / stock_logs to products become "ON DELETE SET NULL",
 * so deleting a product no longer fails with "foreign key constraint fails".
 */
header("Content-Type: text/plain; charset=UTF-8");

if (!in_array($_SERVER["REMOTE_ADDR"] ?? "", ["127.0.0.1", "::1"], true)) {
    http_response_code(403);
    exit("fix-product-delete.php only works from localhost.");
}

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";

function say(string $m): void { echo $m . "\n"; }

say("=== Make products deletable ===\n");

try {

    db_add_column($pdo, "order_items", "product_name", "VARCHAR(150) NULL");

    $pdo->exec("
        UPDATE order_items oi
        INNER JOIN products p ON p.id = oi.product_id
        SET oi.product_name = p.product_name
        WHERE oi.product_name IS NULL
    ");
    say("+ product names saved on sold items");

    db_make_nullable($pdo, "order_items", "product_id");
    db_make_nullable($pdo, "stock_logs", "product_id");
    say("+ product_id may now be empty on order_items and stock_logs");

} catch (Throwable $e) {
    say("! FAILED: " . $e->getMessage());
}

foreach (["order_items", "stock_logs"] as $table) {

    try {

        if (!db_table_exists($pdo, $table)) {
            say("- $table: table not found, skipped");
            continue;
        }

        $stmt = $pdo->prepare("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = 'product_id'
              AND REFERENCED_TABLE_NAME = 'products'
        ");
        $stmt->execute([$table]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $constraint) {
            $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
            say("+ $table: removed old link `$constraint`");
        }

        $pdo->exec("
            ALTER TABLE `$table`
            ADD CONSTRAINT `fk_{$table}_product`
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
            ON DELETE SET NULL
        ");
        say("+ $table: now uses ON DELETE SET NULL");

    } catch (Throwable $e) {
        say("! $table FAILED: " . $e->getMessage());
    }
}

say("\nDone. You can now delete products in phpMyAdmin. Delete fix-product-delete.php.");
