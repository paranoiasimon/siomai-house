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

    $id = isset($data["id"])
        ? (int)$data["id"]
        : 0;

    if ($id <= 0) {
        throw new Exception("Invalid product ID.");
    }

    $check = $pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
    $check->execute([$id]);

    if (!$check->fetch()) {
        throw new Exception("Product not found.");
    }

    $stmt = $pdo->prepare("
        UPDATE products
        SET status = 'Available'
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    echo json_encode([
        "success" => true,
        "message" => "Product enabled successfully."
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
