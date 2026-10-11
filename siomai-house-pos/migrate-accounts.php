<?php
session_start();
/*
 * ONE-TIME migration: separates client accounts from staff accounts.
 *
 *   staff / owner / admin / cashier  ->  table `users`   (unchanged)
 *   clients (buyers)                 ->  table `clients` (new)
 *
 * Open in your browser:  http://localhost/siomai-house-pos/migrate-accounts.php
 * Works from localhost only. Safe to run again. Delete it when finished.
 */
header("Content-Type: text/plain; charset=UTF-8");

$ip = $_SERVER["REMOTE_ADDR"] ?? "";

if (!in_array($ip, ["127.0.0.1", "::1"], true)) {
    http_response_code(403);
    exit("migrate-accounts.php only works from localhost.");
}

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";

function say(string $message): void
{
    echo $message . "\n";
}

function run_step(string $title, callable $step): void
{
    say($title);

    try {
        $step();
    } catch (Throwable $e) {
        say("  ! FAILED: " . $e->getMessage());
    }
}

say("=== Siomai House: separate client accounts ===\n");

run_step("1. Create the clients table", function () use ($pdo) {
    ensure_clients_table($pdo);
    say("  + clients table is ready");
});

run_step("2. Update orders and customers", function () use ($pdo) {
    db_add_column($pdo, "orders", "client_id", "INT UNSIGNED NULL");
    db_make_nullable($pdo, "orders", "user_id");
    db_add_column($pdo, "customers", "client_id", "INT UNSIGNED NULL");
    db_add_column($pdo, "customers", "user_id", "INT UNSIGNED NULL");
    say("  + orders and customers are ready");
});

run_step("4. Let logs be saved for client activity, add speed-up indexes", function () use ($pdo) {
    unset($_SESSION["account_schema_v2"]);
    ensure_account_split_schema($pdo);
    say("  + logs, client columns and indexes are ready");
});

run_step("5. Move existing client accounts out of users", function () use ($pdo) {

    $roleId = $pdo->query("SELECT id FROM roles WHERE LOWER(role_name) = 'client' LIMIT 1")->fetchColumn();

    if (!$roleId) {
        say("  - no Client role found, nothing to move");
        return;
    }

    $stmt = $pdo->prepare("SELECT id, name, email, password, status FROM users WHERE role_id = ?");
    $stmt->execute([$roleId]);
    $accounts = $stmt->fetchAll();

    if (!$accounts) {
        say("  - no client accounts inside users, nothing to move");
        return;
    }

    $hasCustomersClient = db_table_exists($pdo, "customers") && db_column_info($pdo, "customers", "client_id");
    $hasCustomersUser   = db_table_exists($pdo, "customers") && db_column_info($pdo, "customers", "user_id");
    $hasStockLogs       = db_table_exists($pdo, "stock_logs");
    $hasLoginLogs       = db_table_exists($pdo, "login_logs");

    foreach ($accounts as $account) {

        $pdo->beginTransaction();

        try {

            $existing = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
            $existing->execute([$account["email"]]);
            $clientId = $existing->fetchColumn();

            if (!$clientId) {
                $pdo->prepare("
                    INSERT INTO clients (name, email, password, status)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $account["name"],
                    $account["email"],
                    $account["password"],
                    $account["status"] === "Inactive" ? "Inactive" : "Active"
                ]);

                $clientId = $pdo->lastInsertId();
            }

            $pdo->prepare("UPDATE orders SET client_id = ?, user_id = NULL WHERE user_id = ?")
                ->execute([$clientId, $account["id"]]);

            if ($hasCustomersClient && $hasCustomersUser) {
                $pdo->prepare("UPDATE customers SET client_id = ?, user_id = NULL WHERE user_id = ?")
                    ->execute([$clientId, $account["id"]]);
            }

            if ($hasStockLogs) {
                $pdo->prepare("UPDATE stock_logs SET user_id = NULL WHERE user_id = ?")
                    ->execute([$account["id"]]);
            }

            if ($hasLoginLogs) {
                $pdo->prepare("UPDATE login_logs SET user_id = NULL WHERE user_id = ?")
                    ->execute([$account["id"]]);
            }

            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$account["id"]]);

            $pdo->commit();

            say("  + moved {$account["email"]}");

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            say("  ! could not move {$account["email"]}: " . $e->getMessage());
        }
    }
});

say("\nDone. Client accounts now live in `clients`; staff, owner and admin stay in `users`.");
say("You can delete migrate-accounts.php now.");
