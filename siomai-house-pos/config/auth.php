<?php

/*
 * Session helpers. Roles are matched by NAME (role IDs differ between databases).
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_login(): void
{
    if (!isset($_SESSION["user_id"])) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Not logged in. Please login again."
        ]);
        exit;
    }
}

function require_role(array $roleIds): void
{
    require_login();

    if (!in_array((int)$_SESSION["role_id"], $roleIds, true)) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "You do not have permission to do this."
        ]);
        exit;
    }
}

/*
 * Staff-level accounts: Admin, Owner, Cashier, Staff (matched by role NAME).
 * Any other role (Client / buyer) is limited to the POS.
 */
function require_staff(): void
{
    require_login();

    $role = strtolower(trim($_SESSION["role_name"] ?? ""));

    if (!in_array($role, ["admin", "owner", "cashier", "staff"], true)) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "You do not have permission to do this."
        ]);
        exit;
    }
}

function is_staff_session(): bool
{
    $role = strtolower(trim($_SESSION["role_name"] ?? ""));

    return in_array($role, ["admin", "owner", "cashier", "staff"], true);
}

/*
 * Only Admin and Owner can manage user accounts.
 */
function require_admin(): void
{
    require_login();

    $role = strtolower(trim($_SESSION["role_name"] ?? ""));

    if (!in_array($role, ["admin", "owner"], true)) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Only the Admin or Owner can manage user accounts."
        ]);
        exit;
    }
}
