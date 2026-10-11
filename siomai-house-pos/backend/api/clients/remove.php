<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";
require_once "../../../config/helpers.php";

require_admin();
require_post();

require_once "../../../config/database.php";

try {

    $id = (int)(json_input()["id"] ?? 0);

    if ($id <= 0) {
        fail("Invalid client ID.");
    }

    $check = $pdo->prepare("SELECT name FROM clients WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $client = $check->fetch();

    if (!$client) {
        fail("Client not found.", 404);
    }

    /*
     * Clients who already ordered are kept for the sales records.
     */
    try {
        $orders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ?");
        $orders->execute([$id]);

        if ((int)$orders->fetchColumn() > 0) {
            fail("\"" . $client["name"] . "\" has orders and cannot be deleted. Set the account to Inactive instead.");
        }
    } catch (PDOException $e) {
        // orders.client_id does not exist yet, so there are no client orders
    }

    try {
        $pdo->prepare("UPDATE customers SET client_id = NULL WHERE client_id = ?")->execute([$id]);
    } catch (Throwable $e) {
        // customers.client_id does not exist yet
    }

    $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);

    echo json_encode([
        "success" => true,
        "message" => "Client deleted successfully."
    ]);

} catch (Throwable $e) {

    fail($e->getMessage(), 500);
}
