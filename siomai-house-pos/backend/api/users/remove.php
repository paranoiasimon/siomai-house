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
        fail("Invalid user ID.");
    }

    if ($id === (int)$_SESSION["user_id"]) {
        fail("You cannot delete your own account.");
    }

    $check = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $user = $check->fetch();

    if (!$user) {
        fail("User not found.", 404);
    }

    /*
     * Accounts with sales or stock history are kept for the records.
     */
    $orders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $orders->execute([$id]);

    $stock = $pdo->prepare("SELECT COUNT(*) FROM stock_logs WHERE user_id = ?");
    $stock->execute([$id]);

    if ((int)$orders->fetchColumn() > 0 || (int)$stock->fetchColumn() > 0) {
        fail("\"" . $user["name"] . "\" has order or stock records and cannot be deleted. Set the account to Inactive instead.");
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare("UPDATE customers SET user_id = NULL WHERE user_id = ?")->execute([$id]);
    } catch (Throwable $e) {
        // customers.user_id does not exist yet
    }

    try {
        $pdo->prepare("UPDATE login_logs SET user_id = NULL WHERE user_id = ?")->execute([$id]);
    } catch (Throwable $e) {
        $pdo->prepare("DELETE FROM login_logs WHERE user_id = ?")->execute([$id]);
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "User deleted successfully."
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail($e->getMessage(), 500);
}
