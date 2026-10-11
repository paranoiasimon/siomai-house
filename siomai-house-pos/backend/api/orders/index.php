<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";

require_staff();

require_once "../../../config/database.php";

try {

    /*
     * Find the date column of the orders table
     * (created_at, order_date, ...) so this works with older databases.
     */
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

    /*
     * Filters
     */
    $search  = trim($_GET["search"] ?? "");
    $payment = trim($_GET["payment_method"] ?? "");
    $from    = trim($_GET["from"] ?? "");
    $to      = trim($_GET["to"] ?? "");

    $where  = [];
    $params = [];

    if ($search !== "") {
        $where[]  = "(o.id = ? OR $cashierExpr LIKE ?)";
        $params[] = ctype_digit(ltrim($search, "#")) ? (int)ltrim($search, "#") : 0;
        $params[] = "%" . $search . "%";
    }

    if (in_array($payment, ["Cash", "GCash", "Card"], true)) {
        $where[]  = "o.payment_method = ?";
        $params[] = $payment;
    }

    if ($dateColumn && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where[]  = "DATE($dateExpr) >= ?";
        $params[] = $from;
    }

    if ($dateColumn && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where[]  = "DATE($dateExpr) <= ?";
        $params[] = $to;
    }

    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "
        SELECT
            o.id,
            $dateExpr AS created_at,
            o.total_amount,
            o.payment_method,
            o.amount_paid,
            o.cash_received,
            o.change_amount,
            o.status,
            $cashierExpr AS cashier_name,
            (
                SELECT COALESCE(SUM(oi.quantity), 0)
                FROM order_items oi
                WHERE oi.order_id = o.id
            ) AS items_count
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        $clientJoin
        $whereSql
        ORDER BY o.id DESC
        LIMIT 300
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Summary (all orders, not affected by filters)
     */
    $summary = $pdo->query("
        SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_sales
        FROM orders
        WHERE status <> 'Cancelled'
    ")->fetch(PDO::FETCH_ASSOC);

    $todayOrders = 0;
    $todaySales  = 0;

    if ($dateColumn) {
        $today = $pdo->query("
            SELECT
                COUNT(*) AS orders_count,
                COALESCE(SUM(total_amount), 0) AS sales
            FROM orders o
            WHERE o.status <> 'Cancelled'
              AND DATE($dateExpr) = CURDATE()
        ")->fetch(PDO::FETCH_ASSOC);

        $todayOrders = (int)$today["orders_count"];
        $todaySales  = (float)$today["sales"];
    }

    echo json_encode([
        "success" => true,
        "orders"  => $orders,
        "summary" => [
            "total_orders"  => (int)$summary["total_orders"],
            "total_sales"   => (float)$summary["total_sales"],
            "today_orders"  => $todayOrders,
            "today_sales"   => $todaySales,
            "has_dates"     => (bool)$dateColumn
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
