<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_staff();
require_post();

require_once "../../../config/database.php";

try {

    require_once "_schema.php";

    $id = (int)(json_input()["id"] ?? 0);

    if ($id <= 0) {
        fail("Invalid customer ID.");
    }

    $pdo->beginTransaction();

    // Keep the orders, just detach them from the deleted customer.
    $pdo->prepare("UPDATE orders SET customer_id = NULL WHERE customer_id = ?")->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        fail("Customer not found.", 404);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Customer deleted successfully."
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail($e->getMessage(), 500);
}
