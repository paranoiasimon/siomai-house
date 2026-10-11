<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_staff();
require_post();

require_once "../../../config/database.php";

try {

    $data = json_input();

    $productId = (int)($data["product_id"] ?? 0);
    $type      = $data["type"] ?? "";
    $quantity  = (int)($data["quantity"] ?? 0);

    if ($productId <= 0) {
        fail("Invalid product.");
    }

    if (!in_array($type, ["add", "remove"], true)) {
        fail("Invalid adjustment type.");
    }

    if ($quantity <= 0) {
        fail("Quantity must be at least 1.");
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT product_name, stock FROM products WHERE id = ? FOR UPDATE");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        $pdo->rollBack();
        fail("Product not found.", 404);
    }

    $current = (int)$product["stock"];
    $newStock = $type === "add" ? $current + $quantity : $current - $quantity;

    if ($newStock < 0) {
        $pdo->rollBack();
        fail("Cannot remove $quantity. Only $current in stock.");
    }

    $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$newStock, $productId]);

    /*
     * Write to the stock log. If your stock_logs table rejects the
     * action text, the stock change itself is still saved.
     */
    try {
        $pdo->prepare("
            INSERT INTO stock_logs (product_id, user_id, action, quantity)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $productId,
            (int)$_SESSION["user_id"],
            $type === "add" ? "RESTOCK" : "ADJUSTMENT",
            $quantity
        ]);
    } catch (Throwable $logError) {
        // ignore log failure
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => $product["product_name"] . " stock is now " . $newStock . ".",
        "stock"   => $newStock
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail($e->getMessage(), 500);
}
