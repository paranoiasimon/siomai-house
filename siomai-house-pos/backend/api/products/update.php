<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";

require_staff();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
    exit;
}

require_once "../../../config/database.php";

try {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$data) {
        throw new Exception("Invalid JSON request.");
    }

    $id = isset($data["id"])
        ? (int)$data["id"]
        : 0;

    $productName = trim($data["product_name"] ?? "");
    $category = trim($data["category"] ?? "");
    $price = isset($data["price"])
        ? (float)$data["price"]
        : 0;
    $stock = isset($data["stock"])
        ? (int)$data["stock"]
        : 0;
    $status = $data["status"] ?? "Available";
    $image = trim($data["image"] ?? "");

    if ($id <= 0) {
        throw new Exception("Invalid product ID.");
    }

    if ($productName === "") {
        throw new Exception("Product name is required.");
    }

    if ($price < 0) {
        throw new Exception("Price cannot be negative.");
    }

    if ($stock < 0) {
        throw new Exception("Stock cannot be negative.");
    }

    if (!in_array(
        $status,
        ["Available", "Unavailable"],
        true
    )) {
        throw new Exception("Invalid product status.");
    }

    $check = $pdo->prepare("
        SELECT id
        FROM products
        WHERE product_name = ?
          AND id <> ?
        LIMIT 1
    ");

    $check->execute([
        $productName,
        $id
    ]);

    if ($check->fetch()) {
        throw new Exception(
            "Another product already uses this name."
        );
    }

    $stmt = $pdo->prepare("
        UPDATE products
        SET
            product_name = ?,
            category = ?,
            price = ?,
            stock = ?,
            status = ?,
            image = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $productName,
        $category,
        $price,
        $stock,
        $status,
        $image !== "" ? $image : null,
        $id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Product updated successfully."
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}