<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_admin();
require_post();

require_once "../../../config/database.php";

try {

    ensure_clients_table($pdo);

    $data = json_input();

    $id       = (int)($data["id"] ?? 0);
    $name     = trim($data["name"] ?? "");
    $email    = trim($data["email"] ?? "");
    $phone    = trim($data["phone"] ?? "");
    $address  = trim($data["address"] ?? "");
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

    if ($id === 0 && strlen($password) < 6) {
        fail("Password must contain at least 6 characters.");
    }

    if ($id > 0 && $password !== "" && strlen($password) < 6) {
        fail("New password must contain at least 6 characters.");
    }

    if (email_in_clients($pdo, $email, $id) || email_in_users($pdo, $email)) {
        fail("This email is already used by another account.");
    }

    $phone   = $phone !== "" ? $phone : null;
    $address = $address !== "" ? $address : null;

    if ($id > 0) {

        $exists = $pdo->prepare("SELECT id FROM clients WHERE id = ? LIMIT 1");
        $exists->execute([$id]);

        if (!$exists->fetch()) {
            fail("Client not found.", 404);
        }

        if ($password !== "") {

            $pdo->prepare("
                UPDATE clients
                SET name = ?, email = ?, phone = ?, address = ?, status = ?, password = ?
                WHERE id = ?
            ")->execute([$name, $email, $phone, $address, $status, password_hash($password, PASSWORD_DEFAULT), $id]);

        } else {

            $pdo->prepare("
                UPDATE clients
                SET name = ?, email = ?, phone = ?, address = ?, status = ?
                WHERE id = ?
            ")->execute([$name, $email, $phone, $address, $status, $id]);
        }

        $message = "Client updated successfully.";

    } else {

        $pdo->prepare("
            INSERT INTO clients (name, email, password, phone, address, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $address, $status]);

        $message = "Client created successfully.";
    }

    echo json_encode([
        "success" => true,
        "message" => $message
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
