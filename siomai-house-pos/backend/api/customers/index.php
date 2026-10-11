<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_staff();

require_once "../../../config/database.php";

try {

    require_once "_schema.php";

    /*
     * Buyers whose orders are not linked to a customer yet: add them now.
     */

    // accounts from the clients table
    $pending = $pdo->query("
        SELECT DISTINCT c.id, c.name, c.email
        FROM orders o
        INNER JOIN clients c ON c.id = o.client_id
        WHERE o.customer_id IS NULL
    ")->fetchAll();

    foreach ($pending as $row) {
        $customerId = ensure_customer_for_account($pdo, "client_id", (int)$row["id"], $row["name"], (string)$row["email"]);

        if ($customerId) {
            $pdo->prepare("UPDATE orders SET customer_id = ? WHERE client_id = ? AND customer_id IS NULL")
                ->execute([$customerId, $row["id"]]);
        }
    }

    // older client accounts that are still inside users
    $legacy = $pdo->query("
        SELECT DISTINCT u.id, u.name, u.email
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE o.customer_id IS NULL
          AND LOWER(r.role_name) NOT IN ('admin', 'owner', 'cashier', 'staff')
    ")->fetchAll();

    foreach ($legacy as $row) {
        $customerId = ensure_customer_for_account($pdo, "user_id", (int)$row["id"], $row["name"], (string)$row["email"]);

        if ($customerId) {
            $pdo->prepare("UPDATE orders SET customer_id = ? WHERE user_id = ? AND customer_id IS NULL")
                ->execute([$customerId, $row["id"]]);
        }
    }

    $search = trim($_GET["search"] ?? "");

    $where = "";
    $params = [];

    if ($search !== "") {
        $where = "WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?";
        $params = ["%$search%", "%$search%", "%$search%"];
    }

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.name,
            c.phone,
            c.email,
            c.address,
            c.client_id,
            c.user_id,
            (
                SELECT COUNT(*) FROM orders o
                WHERE o.customer_id = c.id AND o.status <> 'Cancelled'
            ) AS orders_count,
            (
                SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o
                WHERE o.customer_id = c.id AND o.status <> 'Cancelled'
            ) AS total_spent
        FROM customers c
        $where
        ORDER BY c.name ASC
        LIMIT 500
    ");

    $stmt->execute($params);

    echo json_encode([
        "success" => true,
        "customers" => $stmt->fetchAll()
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
