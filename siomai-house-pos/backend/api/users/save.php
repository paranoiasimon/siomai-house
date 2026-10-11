<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_admin();
require_post();

require_once "../../../config/database.php";

try {

    $data = json_input();

    $id       = (int)($data["id"] ?? 0);
    $name     = trim($data["name"] ?? "");
    $email    = trim($data["email"] ?? "");
    $roleId   = (int)($data["role_id"] ?? 0);
    $status   = $data["status"] ?? "Active";
    $password = (string)($data["password"] ?? "");

    if ($name === "") {
        fail("Name is required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail("Please enter a valid email address.");
    }

    if (!in_array($status, ["Active", "Inactive"], true)) {
        fail("Invalid status.");
    }

    $roleCheck = $pdo->prepare("SELECT role_name FROM roles WHERE id = ? LIMIT 1");
    $roleCheck->execute([$roleId]);
    $roleName = $roleCheck->fetchColumn();

    if (!$roleName) {
        fail("Please choose a valid role.");
    }

    if (strtolower($roleName) === "client") {
        fail("Client accounts are managed in the Client Accounts table.");
    }

    if ($id === 0 && strlen($password) < 6) {
        fail("Password must contain at least 6 characters.");
    }

    if ($id > 0 && $password !== "" && strlen($password) < 6) {
        fail("New password must contain at least 6 characters.");
    }

    if (email_in_users($pdo, $email, $id) || email_in_clients($pdo, $email)) {
        fail("This email is already used by another account.");
    }

    if ($id > 0) {

        $existing = $pdo->prepare("SELECT role_id, status FROM users WHERE id = ? LIMIT 1");
        $existing->execute([$id]);
        $current = $existing->fetch();

        if (!$current) {
            fail("User not found.", 404);
        }

        // Do not let admins lock themselves out.
        if ($id === (int)$_SESSION["user_id"]) {

            if ($status !== "Active") {
                fail("You cannot deactivate your own account.");
            }

            if ((int)$current["role_id"] !== $roleId) {
                fail("You cannot change your own role.");
            }
        }

        if ($password !== "") {

            $pdo->prepare("
                UPDATE users
                SET name = ?, email = ?, role_id = ?, status = ?, password = ?
                WHERE id = ?
            ")->execute([$name, $email, $roleId, $status, password_hash($password, PASSWORD_DEFAULT), $id]);

        } else {

            $pdo->prepare("
                UPDATE users
                SET name = ?, email = ?, role_id = ?, status = ?
                WHERE id = ?
            ")->execute([$name, $email, $roleId, $status, $id]);
        }

        $message = "User updated successfully.";

    } else {

        $pdo->prepare("
            INSERT INTO users (role_id, name, email, password, status)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$roleId, $name, $email, password_hash($password, PASSWORD_DEFAULT), $status]);

        $message = "User created successfully.";
    }

    echo json_encode([
        "success" => true,
        "message" => $message
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
