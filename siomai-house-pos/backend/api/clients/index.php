<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_admin();

require_once "../../../config/database.php";

try {

    ensure_clients_table($pdo);

    $search = trim($_GET["search"] ?? "");

    $where = "";
    $params = [];

    if ($search !== "") {
        $where = "WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?";
        $params = ["%$search%", "%$search%", "%$search%"];
    }

    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, address, status, created_at
        FROM clients
        $where
        ORDER BY id ASC
        LIMIT 500
    ");

    $stmt->execute($params);

    echo json_encode([
        "success" => true,
        "clients" => $stmt->fetchAll()
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
