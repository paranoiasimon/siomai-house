<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_staff();
require_post();

require_once "../../../config/database.php";

try {

    require_once "_schema.php";

    $data = json_input();

    $id      = (int)($data["id"] ?? 0);
    $name    = trim($data["name"] ?? "");
    $phone   = trim($data["phone"] ?? "");
    $email   = trim($data["email"] ?? "");
    $address = trim($data["address"] ?? "");

    if ($name === "") {
        fail("Customer name is required.");
    }

    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail("Please enter a valid email address.");
    }

    $values = [
        $name,
        $phone !== "" ? $phone : null,
        $email !== "" ? $email : null,
        $address !== "" ? $address : null,
    ];

    if ($id > 0) {

        $stmt = $pdo->prepare("
            UPDATE customers
            SET name = ?, phone = ?, email = ?, address = ?
            WHERE id = ?
        ");

        $stmt->execute([...$values, $id]);

        $message = "Customer updated successfully.";

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO customers (name, phone, email, address)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute($values);

        $message = "Customer added successfully.";
    }

    echo json_encode([
        "success" => true,
        "message" => $message
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
