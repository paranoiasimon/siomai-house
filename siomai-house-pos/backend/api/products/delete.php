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

    $stmt = $pdo->prepare("
        UPDATE products
        SET status = 'Unavailable'
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("Product not found.");
    }

    echo json_encode([
        "success" => true,
        "message" => "Product disabled successfully."
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}