<?php

require_once "../../../config/cors.php";
require_once "../../../config/database.php";

session_start();

if (isset($_SESSION["login_log_id"])) {

    try {

        $sql = "
            UPDATE login_logs
            SET logout_time = NOW()
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $_SESSION["login_log_id"]
        ]);

    } catch (PDOException $e) {

        // Continue logout even if log update fails.
    }
}

/*
 * Clear session
 */
$_SESSION = [];

/*
 * Destroy session
 */
session_destroy();

echo json_encode([
    "success" => true,
    "message" => "Logout successful."
]);