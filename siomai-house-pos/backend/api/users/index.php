<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_admin();

require_once "../../../config/database.php";

try {

    $search = trim($_GET["search"] ?? "");
    $roleId = (int)($_GET["role_id"] ?? 0);

    $where = [];
    $params = [];

    if ($search !== "") {
        $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($roleId > 0) {
        $where[] = "u.role_id = ?";
        $params[] = $roleId;
    }

    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    $dateColumn = find_date_column($pdo, "users");
    $dateExpr = $dateColumn ? "u.`$dateColumn`" : "NULL";

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.role_id,
            r.role_name,
            u.status,
            $dateExpr AS created_at
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        $whereSql
        ORDER BY u.id ASC
        LIMIT 500
    ");

    $stmt->execute($params);

    // Client is not a staff role: client accounts have their own table.
    $roles = $pdo->query("SELECT id, role_name FROM roles WHERE LOWER(role_name) <> 'client' ORDER BY id ASC")->fetchAll();

    echo json_encode([
        "success" => true,
        "users"   => $stmt->fetchAll(),
        "roles"   => $roles,
        "me"      => (int)$_SESSION["user_id"]
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
