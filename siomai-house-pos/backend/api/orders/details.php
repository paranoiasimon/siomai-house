<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_staff();

require_once "../../../config/database.php";

try {

    $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

    if ($id <= 0) {
        throw new Exception("Invalid order ID.");
    }

    $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);

    $dateColumn = null;

    foreach (["created_at", "order_date", "date_created", "created", "order_datetime", "datetime", "date"] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $dateColumn = $candidate;
            break;
        }
    }

    $dateExpr = $dateColumn ? "o.`$dateColumn`" : "NULL";

    // Client orders have no cashier: show the client's name instead.
    $hasClient = (bool)$pdo->query("SHOW COLUMNS FROM orders LIKE 'client_id'")->fetch();

    $cashierExpr = $hasClient
        ? "COALESCE(u.name, CONCAT(cl.name, ' (client)'))"
        : "u.name";

    $clientJoin = $hasClient
        ? "LEFT JOIN clients cl ON cl.id = o.client_id"
        : "";

    $stmt = $pdo->prepare("
        SELECT
            o.id,
            $dateExpr AS created_at,
            o.total_amount,
            o.payment_method,
            o.amount_paid,
            o.cash_received,
            o.change_amount,
            o.status,
            $cashierExpr AS cashier_name
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        $clientJoin
        WHERE o.id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        throw new Exception("Order not found.");
    }

    $nameExpr = db_column_info($pdo, "order_items", "product_name")
        ? "COALESCE(p.product_name, oi.product_name)"
        : "p.product_name";

    $itemsStmt = $pdo->prepare("
        SELECT
            oi.id,
            oi.product_id,
            $nameExpr AS product_name,
            oi.quantity,
            oi.price,
            oi.subtotal
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");

    $itemsStmt->execute([$id]);

    echo json_encode([
        "success" => true,
        "order"   => $order,
        "items"   => $itemsStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {

    if (http_response_code() < 400) {
        http_response_code(400);
    }

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
