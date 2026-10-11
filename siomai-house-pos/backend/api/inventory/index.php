<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_staff();

require_once "../../../config/database.php";

const LOW_STOCK_LIMIT = 10;

try {

    $products = $pdo->query("
        SELECT id, product_name, category, price, stock, status, image
        FROM products
        ORDER BY product_name ASC
    ")->fetchAll();

    $low = 0;
    $out = 0;
    $value = 0;

    foreach ($products as $p) {
        $stock = (int)$p["stock"];
        $value += $stock * (float)$p["price"];

        if ($stock <= 0) {
            $out++;
        } elseif ($stock <= LOW_STOCK_LIMIT) {
            $low++;
        }
    }

    /*
     * Recent stock movements
     */
    $logs = [];

    try {

        $columns = $pdo->query("SHOW COLUMNS FROM stock_logs")->fetchAll(PDO::FETCH_COLUMN);
        $dateColumn = find_date_column($pdo, "stock_logs");

        $dateExpr = $dateColumn ? "l.`$dateColumn`" : "NULL";
        $orderBy  = in_array("id", $columns, true) ? "l.id DESC" : "l.product_id DESC";

        $logs = $pdo->query("
            SELECT
                $dateExpr AS created_at,
                p.product_name,
                u.name AS user_name,
                l.action,
                l.quantity
            FROM stock_logs l
            LEFT JOIN products p ON p.id = l.product_id
            LEFT JOIN users u ON u.id = l.user_id
            ORDER BY $orderBy
            LIMIT 40
        ")->fetchAll();

    } catch (Throwable $e) {
        $logs = [];
    }

    echo json_encode([
        "success" => true,
        "low_stock_limit" => LOW_STOCK_LIMIT,
        "summary" => [
            "total_products" => count($products),
            "low_stock"      => $low,
            "out_of_stock"   => $out,
            "stock_value"    => $value
        ],
        "products" => $products,
        "logs"     => $logs
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
