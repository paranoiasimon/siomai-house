<?php

require_once "../../../config/cors.php";
require_once "../../../config/database.php";

session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);

    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($email === "" || $password === "") {

    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);

    exit;
}

try {

    /*
     * Get user and role
     */
    $sql = "
        SELECT
            u.id,
            u.role_id,
            u.name,
            u.email,
            u.password,
            u.status,
            r.role_name
        FROM users u
        INNER JOIN roles r
            ON u.role_id = r.id
        WHERE u.email = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);

    $user = $stmt->fetch();

    /*
     * Not a staff account? Check the clients table.
     */
    if (!$user) {

        $client = null;

        try {

            $clientStmt = $pdo->prepare("
                SELECT id, name, email, password, status
                FROM clients
                WHERE email = ?
                LIMIT 1
            ");

            $clientStmt->execute([$email]);
            $client = $clientStmt->fetch();

        } catch (PDOException $e) {

            $client = null; // clients table does not exist yet
        }

        if ($client) {

            if ($client["status"] !== "Active") {

                echo json_encode([
                    "success" => false,
                    "message" => "This account is inactive."
                ]);

                exit;
            }

            if (!password_verify($password, $client["password"])) {

                $pdo->prepare("
                    INSERT INTO login_logs (user_id, status, ip_address)
                    VALUES (NULL, 'Failed', ?)
                ")->execute([$_SERVER["REMOTE_ADDR"] ?? null]);

                echo json_encode([
                    "success" => false,
                    "message" => "Invalid email or password."
                ]);

                exit;
            }

            session_regenerate_id(true);

            $_SESSION["user_id"] = (int)$client["id"];
            $_SESSION["user_type"] = "client";
            $_SESSION["user_name"] = $client["name"];
            $_SESSION["user_email"] = $client["email"];
            $_SESSION["role_id"] = 0;
            $_SESSION["role_name"] = "Client";

            try {

                try {

                    $pdo->prepare("
                        INSERT INTO login_logs (user_id, client_id, status, ip_address)
                        VALUES (NULL, ?, 'Success', ?)
                    ")->execute([(int)$client["id"], $_SERVER["REMOTE_ADDR"] ?? null]);

                } catch (PDOException $e) {

                    // login_logs.client_id does not exist yet
                    $pdo->prepare("
                        INSERT INTO login_logs (user_id, status, ip_address)
                        VALUES (NULL, 'Success', ?)
                    ")->execute([$_SERVER["REMOTE_ADDR"] ?? null]);
                }

                $_SESSION["login_log_id"] = $pdo->lastInsertId();

            } catch (PDOException $e) {
                // login still works if the log cannot be written
            }

            echo json_encode([
                "success" => true,
                "message" => "Login successful.",
                "user" => [
                    "id" => (int)$client["id"],
                    "name" => $client["name"],
                    "email" => $client["email"],
                    "role_id" => 0,
                    "role_name" => "Client",
                    "type" => "client"
                ]
            ]);

            exit;
        }
    }

    /*
     * User does not exist
     */
    if (!$user) {

        $logSql = "
            INSERT INTO login_logs
            (user_id, status, ip_address)
            VALUES (NULL, 'Failed', ?)
        ";

        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            $_SERVER["REMOTE_ADDR"] ?? null
        ]);

        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password."
        ]);

        exit;
    }

    /*
     * Check account status
     */
    if ($user["status"] !== "Active") {

        echo json_encode([
            "success" => false,
            "message" => "This account is inactive."
        ]);

        exit;
    }

    /*
     * Verify password
     */
    if (!password_verify($password, $user["password"])) {

        $logSql = "
            INSERT INTO login_logs
            (user_id, status, ip_address)
            VALUES (?, 'Failed', ?)
        ";

        $logStmt = $pdo->prepare($logSql);

        $logStmt->execute([
            $user["id"],
            $_SERVER["REMOTE_ADDR"] ?? null
        ]);

        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password."
        ]);

        exit;
    }

    /*
     * Create PHP session
     */
    session_regenerate_id(true);

    $_SESSION["user_id"] = $user["id"];
    $_SESSION["user_type"] = "staff";
    $_SESSION["user_name"] = $user["name"];
    $_SESSION["user_email"] = $user["email"];
    $_SESSION["role_id"] = $user["role_id"];
    $_SESSION["role_name"] = $user["role_name"];

    /*
     * Save successful login
     */
    $logSql = "
        INSERT INTO login_logs
        (user_id, status, ip_address)
        VALUES (?, 'Success', ?)
    ";

    $logStmt = $pdo->prepare($logSql);

    $logStmt->execute([
        $user["id"],
        $_SERVER["REMOTE_ADDR"] ?? null
    ]);

    $loginLogId = $pdo->lastInsertId();

    $_SESSION["login_log_id"] = $loginLogId;

    echo json_encode([
        "success" => true,
        "message" => "Login successful.",
        "user" => [
            "id" => (int)$user["id"],
            "name" => $user["name"],
            "email" => $user["email"],
            "role_id" => (int)$user["role_id"],
            "role_name" => $user["role_name"]
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Login failed because of a database error."
    ]);
}