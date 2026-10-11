<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_staff();

require_once "../../../config/database.php";

try {

    $todaySales  = 0;
    $todayOrders = 0;

    $dateColumn = find_date_column($pdo, "orders");

    if ($dateColumn) {

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS orders_count,
                   COALESCE(SUM(total_amount), 0) AS sales
            FROM orders
            WHERE status <> 'Cancelled'
              AND `$dateColumn` >= ?
              AND `$dateColumn` < ?
        ");

        $stmt->execute([
            date("Y-m-d") . " 00:00:00",
            date("Y-m-d", strtotime("+1 day")) . " 00:00:00"
        ]);

        $row = $stmt->fetch();
        $todayOrders = (int)$row["orders_count"];
        $todaySales  = (float)$row["sales"];
    }

    $stock = $pdo->query("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN stock <= 10 THEN 1 ELSE 0 END) AS low
        FROM products
    ")->fetch();

    echo json_encode([
        "success"      => true,
        "today_sales"  => $todaySales,
        "today_orders" => $todayOrders,
        "products"     => (int)$stock["total"],
        "low_stock"    => (int)$stock["low"]
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
