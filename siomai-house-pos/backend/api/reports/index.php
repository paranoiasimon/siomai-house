<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_staff();

require_once "../../../config/database.php";

try {

    $dateColumn = find_date_column($pdo, "orders");

    if (!$dateColumn) {
        fail("Your orders table has no date column, so reports cannot be built. See the note in the README / ask for the one-line SQL fix.");
    }

    $col = "o.`$dateColumn`";

    /*
     * Date range (default: last 30 days, max 1 year)
     */
    $valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;

    $to   = $valid($_GET["to"] ?? "")   ? $_GET["to"]   : date("Y-m-d");
    $from = $valid($_GET["from"] ?? "") ? $_GET["from"] : date("Y-m-d", strtotime("$to -29 days"));

    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }

    if (strtotime($to) - strtotime($from) > 366 * 86400) {
        $from = date("Y-m-d", strtotime("$to -365 days"));
    }

    // index-friendly range: from 00:00 up to (not including) the next day
    $start = $from . " 00:00:00";
    $end   = date("Y-m-d", strtotime("$to +1 day")) . " 00:00:00";

    $range = "$col >= ? AND $col < ? AND o.status <> 'Cancelled'";
    $args  = [$start, $end];

    /*
     * Summary
     */
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS orders_count,
               COALESCE(SUM(o.total_amount), 0) AS revenue
        FROM orders o
        WHERE $range
    ");
    $stmt->execute($args);
    $sum = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(oi.quantity), 0)
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE $range
    ");
    $stmt->execute($args);
    $itemsSold = (int)$stmt->fetchColumn();

    $ordersCount = (int)$sum["orders_count"];
    $revenue     = (float)$sum["revenue"];

    /*
     * Sales per day (days without sales are filled with zero)
     */
    $stmt = $pdo->prepare("
        SELECT DATE($col) AS day,
               COUNT(*) AS orders_count,
               SUM(o.total_amount) AS sales
        FROM orders o
        WHERE $range
        GROUP BY DATE($col)
    ");
    $stmt->execute($args);

    $byDay = [];
    foreach ($stmt->fetchAll() as $row) {
        $byDay[$row["day"]] = $row;
    }

    $daily = [];
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
        $d = date("Y-m-d", $t);
        $daily[] = [
            "day"    => $d,
            "orders" => (int)($byDay[$d]["orders_count"] ?? 0),
            "sales"  => (float)($byDay[$d]["sales"] ?? 0),
        ];
    }

    $nameExpr = db_column_info($pdo, "order_items", "product_name")
        ? "COALESCE(p.product_name, oi.product_name)"
        : "p.product_name";

    /*
     * Top products
     */
    $stmt = $pdo->prepare("
        SELECT $nameExpr AS product_name,
               SUM(oi.quantity) AS quantity,
               SUM(oi.subtotal) AS revenue
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE $range
        GROUP BY oi.product_id, $nameExpr
        ORDER BY quantity DESC
        LIMIT 10
    ");
    $stmt->execute($args);
    $topProducts = $stmt->fetchAll();

    /*
     * Payment methods
     */
    $stmt = $pdo->prepare("
        SELECT o.payment_method,
               COUNT(*) AS orders_count,
               SUM(o.total_amount) AS sales
        FROM orders o
        WHERE $range
        GROUP BY o.payment_method
        ORDER BY sales DESC
    ");
    $stmt->execute($args);
    $payments = $stmt->fetchAll();

    /*
     * Categories
     */
    $stmt = $pdo->prepare("
        SELECT COALESCE(NULLIF(p.category, ''), 'Uncategorized') AS category,
               SUM(oi.quantity) AS quantity,
               SUM(oi.subtotal) AS revenue
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE $range
        GROUP BY category
        ORDER BY revenue DESC
        LIMIT 8
    ");
    $stmt->execute($args);
    $categories = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "range"   => ["from" => $from, "to" => $to],
        "summary" => [
            "revenue"       => $revenue,
            "orders"        => $ordersCount,
            "items_sold"    => $itemsSold,
            "average_order" => $ordersCount ? $revenue / $ordersCount : 0,
        ],
        "daily"        => $daily,
        "top_products" => $topProducts,
        "payments"     => $payments,
        "categories"   => $categories,
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
