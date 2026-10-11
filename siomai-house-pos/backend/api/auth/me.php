<?php

require_once "../../../config/cors.php";

session_start();

if (!isset($_SESSION["user_id"])) {

    echo json_encode([
        "success" => false,
        "authenticated" => false,
        "message" => "Not logged in."
    ]);

    exit;
}

echo json_encode([
    "success" => true,
    "authenticated" => true,
    "user" => [
        "id" => (int)$_SESSION["user_id"],
        "name" => $_SESSION["user_name"],
        "email" => $_SESSION["user_email"],
        "role_id" => (int)$_SESSION["role_id"],
        "role_name" => $_SESSION["role_name"],
        "type" => $_SESSION["user_type"] ?? "staff"
    ]
]);