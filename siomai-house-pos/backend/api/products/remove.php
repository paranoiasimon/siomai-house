<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_staff();
require_post();

require_once "../../../config/database.php";

try {

    $id = (int)(json_input()["id"] ?? 0);

    if ($id <= 0) {
        fail("Invalid product ID.");
    }

    $check = $pdo->prepare("SELECT product_name FROM products WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $product = $check->fetch();

    if (!$product) {
        fail("Product not found.", 404);
    }

    /*
     * Table changes first (they cannot be rolled back, so they stay
     * outside the transaction). Sold items keep the product NAME, which
     * lets the product itself be deleted without losing past orders.
     */
    db_add_column($pdo, "order_items", "product_name", "VARCHAR(150) NULL");
    db_make_nullable($pdo, "order_items", "product_id");
    db_make_nullable($pdo, "stock_logs", "product_id");

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE order_items
        SET product_name = ?
        WHERE product_id = ? AND (product_name IS NULL OR product_name = '')
    ")->execute([$product["product_name"], $id]);

    $pdo->prepare("UPDATE order_items SET product_id = NULL WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("UPDATE stock_logs SET product_id = NULL WHERE product_id = ?")->execute([$id]);

    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Product deleted successfully."
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail("Could not delete the product: " . $e->getMessage(), 500);
}
