<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_admin();

require_once "../../../config/database.php";

try {

    ensure_account_split_schema($pdo);

    $type   = ($_GET["type"] ?? "login") === "stock" ? "stock" : "login";
    $search = trim($_GET["search"] ?? "");
    $from   = trim($_GET["from"] ?? "");
    $to     = trim($_GET["to"] ?? "");

    $isDate = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

    $where = [];
    $args  = [];

    if ($type === "login") {

        $logColumns = $pdo->query("SHOW COLUMNS FROM login_logs")->fetchAll(PDO::FETCH_COLUMN);

        $dateColumn = find_date_column($pdo, "login_logs");
        $dateExpr   = $dateColumn ? "l.`$dateColumn`" : "NULL";
        $logoutExpr = in_array("logout_time", $logColumns, true) ? "l.logout_time" : "NULL";
        $hasClient  = in_array("client_id", $logColumns, true);

        $nameExpr   = $hasClient ? "COALESCE(u.name, c.name)" : "u.name";
        $emailExpr  = $hasClient ? "COALESCE(u.email, c.email)" : "u.email";
        $clientJoin = $hasClient ? "LEFT JOIN clients c ON c.id = l.client_id" : "";

        $accountType = $hasClient
            ? "CASE WHEN l.user_id IS NOT NULL THEN 'Staff' WHEN l.client_id IS NOT NULL THEN 'Client' ELSE 'Unknown' END"
            : "CASE WHEN l.user_id IS NOT NULL THEN 'Staff' ELSE 'Unknown' END";

        $status = $_GET["status"] ?? "";

        if (in_array($status, ["Success", "Failed"], true)) {
            $where[] = "l.status = ?";
            $args[]  = $status;
        }

        if ($search !== "") {
            $where[] = "($nameExpr LIKE ? OR $emailExpr LIKE ? OR l.ip_address LIKE ?)";
            array_push($args, "%$search%", "%$search%", "%$search%");
        }

        if ($dateColumn && $isDate($from)) {
            $where[] = "$dateExpr >= ?";
            $args[]  = "$from 00:00:00";
        }

        if ($dateColumn && $isDate($to)) {
            $where[] = "$dateExpr < ?";
            $args[]  = date("Y-m-d", strtotime("$to +1 day")) . " 00:00:00";
        }

        $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $pdo->prepare("
            SELECT
                l.id,
                $dateExpr AS logged_at,
                $logoutExpr AS logout_at,
                l.status,
                l.ip_address,
                $nameExpr AS account_name,
                $emailExpr AS account_email,
                $accountType AS account_type
            FROM login_logs l
            LEFT JOIN users u ON u.id = l.user_id
            $clientJoin
            $whereSql
            ORDER BY l.id DESC
            LIMIT 300
        ");

        $stmt->execute($args);

        echo json_encode([
            "success" => true,
            "type"    => "login",
            "logs"    => $stmt->fetchAll()
        ]);

        exit;
    }

    /*
     * Stock logs
     */
    $logColumns = $pdo->query("SHOW COLUMNS FROM stock_logs")->fetchAll(PDO::FETCH_COLUMN);

    $dateColumn = find_date_column($pdo, "stock_logs");
    $dateExpr   = $dateColumn ? "l.`$dateColumn`" : "NULL";
    $orderBy    = in_array("id", $logColumns, true) ? "l.id DESC" : "l.product_id DESC";

    if ($search !== "") {
        $where[] = "(p.product_name LIKE ? OR u.name LIKE ?)";
        array_push($args, "%$search%", "%$search%");
    }

    if ($dateColumn && $isDate($from)) {
        $where[] = "$dateExpr >= ?";
        $args[]  = "$from 00:00:00";
    }

    if ($dateColumn && $isDate($to)) {
        $where[] = "$dateExpr < ?";
        $args[]  = date("Y-m-d", strtotime("$to +1 day")) . " 00:00:00";
    }

    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $pdo->prepare("
        SELECT
            $dateExpr AS logged_at,
            p.product_name,
            COALESCE(u.name, 'Client order') AS account_name,
            l.action,
            l.quantity
        FROM stock_logs l
        LEFT JOIN products p ON p.id = l.product_id
        LEFT JOIN users u ON u.id = l.user_id
        $whereSql
        ORDER BY $orderBy
        LIMIT 300
    ");

    $stmt->execute($args);

    echo json_encode([
        "success" => true,
        "type"    => "stock",
        "logs"    => $stmt->fetchAll()
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
