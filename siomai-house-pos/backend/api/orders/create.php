<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
    exit;
}

require_once "../../../config/database.php";

try {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        throw new Exception("Invalid JSON request.");
    }

    /*
     * Staff orders are saved under users.id (user_id).
     * Client orders are saved under clients.id (client_id).
     */
    $isClient  = ($_SESSION["user_type"] ?? "staff") === "client";
    $accountId = (int)$_SESSION["user_id"];
    $userId    = $isClient ? null : $accountId;
    $clientId  = $isClient ? $accountId : null;

    $customerId = !empty($data["customer_id"])
        ? (int)$data["customer_id"]
        : null;

    $paymentMethod = $data["payment_method"] ?? "Cash";

    $amountPaid = isset($data["amount_paid"])
        ? (float)$data["amount_paid"]
        : 0;

    $items = $data["items"] ?? [];

    /*
     * Validate user
     */

    if ($accountId <= 0) {
        throw new Exception("Invalid user ID.");
    }

    /*
     * Validate payment method
     */

    $allowedPaymentMethods = [
        "Cash",
        "GCash",
        "Card"
    ];

    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        throw new Exception("Invalid payment method.");
    }

    /*
     * Validate items
     */

    if (!is_array($items) || count($items) === 0) {
        throw new Exception("Cart is empty.");
    }

    /*
     * Start transaction
     */

    /*
     * Make sure the customers table is ready BEFORE the transaction
     * (table changes cannot be rolled back).
     */
    require_once "../customers/_schema.php";

    $pdo->beginTransaction();

    /*
     * Any buyer (Client) who orders is added to Customers automatically
     * and the order is linked to them.
     */
    if (!is_staff_session()) {
        $customerId = ensure_customer_for_session($pdo);
    }

    $totalAmount = 0;
    $validatedItems = [];

    /*
     * Check every product
     */

    foreach ($items as $item) {

        $productId = isset($item["product_id"])
            ? (int)$item["product_id"]
            : 0;

        $quantity = isset($item["quantity"])
            ? (int)$item["quantity"]
            : 0;

        if ($productId <= 0 || $quantity <= 0) {
            throw new Exception("Invalid product or quantity.");
        }

        /*
         * Lock product row while processing order
         */

        $stmt = $pdo->prepare("
            SELECT
                id,
                product_name,
                price,
                stock,
                status
            FROM products
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([$productId]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception(
                "Product ID " . $productId . " does not exist."
            );
        }

        if ($product["status"] !== "Available") {
            throw new Exception(
                $product["product_name"] . " is unavailable."
            );
        }

        if ((int)$product["stock"] < $quantity) {
            throw new Exception(
                "Not enough stock for " .
                $product["product_name"] .
                ". Available stock: " .
                $product["stock"]
            );
        }

        $price = (float)$product["price"];

        $subtotal = $price * $quantity;

        $totalAmount += $subtotal;

        $validatedItems[] = [
            "product_id" => $productId,
            "quantity" => $quantity,
            "price" => $price,
            "subtotal" => $subtotal,
            "product_name" => $product["product_name"]
        ];
    }

    /*
     * Calculate payment
     */

    $changeAmount = 0;
    $cashReceived = 0;

    if ($paymentMethod === "Cash") {

        $cashReceived = $amountPaid;

        if ($cashReceived < $totalAmount) {
            throw new Exception(
                "Insufficient cash. Total is ₱" .
                number_format($totalAmount, 2) .
                " but only ₱" .
                number_format($cashReceived, 2) .
                " was received."
            );
        }

        $changeAmount = $cashReceived - $totalAmount;

    } else {

        /*
         * GCash/Card
         * payment equals total
         */

        $amountPaid = $totalAmount;
        $cashReceived = 0;
        $changeAmount = 0;
    }

    /*
     * Insert order
     */

    $orderColumns = [
        "customer_id", "user_id", "total_amount", "payment_method",
        "amount_paid", "cash_received", "change_amount", "status"
    ];

    $orderValues = [
        $customerId, $userId, $totalAmount, $paymentMethod,
        $amountPaid, $cashReceived, $changeAmount, "Completed"
    ];

    // Only client orders use the new client_id column.
    if ($clientId !== null) {
        $orderColumns[] = "client_id";
        $orderValues[]  = $clientId;
    }

    $orderStmt = $pdo->prepare(
        "INSERT INTO orders (" . implode(", ", $orderColumns) . ")
         VALUES (" . implode(", ", array_fill(0, count($orderColumns), "?")) . ")"
    );

    $orderStmt->execute($orderValues);

    $orderId = $pdo->lastInsertId();

    /*
     * Insert order items
     */

    $hasItemName = (bool)db_column_info($pdo, "order_items", "product_name");

    $itemStmt = $pdo->prepare(
        $hasItemName
            ? "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, product_name)
               VALUES (?, ?, ?, ?, ?, ?)"
            : "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
               VALUES (?, ?, ?, ?, ?)"
    );

    /*
     * Update stock
     */

    $stockStmt = $pdo->prepare("
        UPDATE products
        SET stock = stock - ?
        WHERE id = ?
          AND stock >= ?
    ");

    /*
     * Stock log
     */

    $stockLogStmt = $pdo->prepare("
        INSERT INTO stock_logs (
            product_id,
            user_id,
            action,
            quantity
        )
        VALUES (?, ?, 'SALE', ?)
    ");

    /*
     * Process every item
     */

    foreach ($validatedItems as $item) {

        /*
         * Save order item
         */

        $itemValues = [
            $orderId,
            $item["product_id"],
            $item["quantity"],
            $item["price"],
            $item["subtotal"]
        ];

        if ($hasItemName) {
            $itemValues[] = $item["product_name"];
        }

        $itemStmt->execute($itemValues);

        /*
         * Deduct stock
         */

        $stockStmt->execute([
            $item["quantity"],
            $item["product_id"],
            $item["quantity"]
        ]);

        if ($stockStmt->rowCount() !== 1) {
            throw new Exception(
                "Unable to update stock for " .
                $item["product_name"]
            );
        }

        /*
         * Save stock log
         */

        $stockLogStmt->execute([
            $item["product_id"],
            $userId,
            $item["quantity"]
        ]);
    }

    /*
     * Everything successful
     */

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Order completed successfully.",
        "order_id" => (int)$orderId,
        "total_amount" => number_format(
            $totalAmount,
            2,
            ".",
            ""
        ),
        "payment_method" => $paymentMethod,
        "amount_paid" => number_format(
            $amountPaid,
            2,
            ".",
            ""
        ),
        "cash_received" => number_format(
            $cashReceived,
            2,
            ".",
            ""
        ),
        "change_amount" => number_format(
            $changeAmount,
            2,
            ".",
            ""
        )
    ]);

} catch (Throwable $e) {

    /*
     * Roll back if transaction is still active
     */

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}