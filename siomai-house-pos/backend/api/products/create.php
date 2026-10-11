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

    $productName = trim($data["product_name"] ?? "");
    $category = trim($data["category"] ?? "");
    $price = isset($data["price"])
        ? (float)$data["price"]
        : 0;
    $stock = isset($data["stock"])
        ? (int)$data["stock"]
        : 0;
    $image = trim($data["image"] ?? "");

    if ($productName === "") {
        throw new Exception("Product name is required.");
    }

    if ($price < 0) {
        throw new Exception("Price cannot be negative.");
    }

    if ($stock < 0) {
        throw new Exception("Stock cannot be negative.");
    }

    $check = $pdo->prepare("
        SELECT id
        FROM products
        WHERE product_name = ?
        LIMIT 1
    ");

    $check->execute([$productName]);

    if ($check->fetch()) {
        throw new Exception(
            "A product with this name already exists."
        );
    }

    $stmt = $pdo->prepare("
        INSERT INTO products (
            product_name,
            category,
            price,
            stock,
            status,
            image
        )
        VALUES (?, ?, ?, ?, 'Available', ?)
    ");

    $stmt->execute([
        $productName,
        $category,
        $price,
        $stock,
        $image !== "" ? $image : null
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Product added successfully.",
        "product_id" => (int)$pdo->lastInsertId()
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}