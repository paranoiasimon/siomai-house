<?php

/*
 * Returns the name of the date column of a table (created_at, order_date, ...)
 * or null when the table has none. Keeps the pages working on older databases.
 */
function find_date_column(PDO $pdo, string $table): ?string
{
    $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);

    foreach (["created_at", "login_time", "order_date", "date_created", "created", "order_datetime", "datetime", "date"] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function json_input(): array
{
    $data = json_decode(file_get_contents("php://input"), true);

    return is_array($data) ? $data : [];
}

function fail(string $message, int $code = 400): void
{
    http_response_code($code);

    echo json_encode([
        "success" => false,
        "message" => $message
    ]);

    exit;
}

function require_post(): void
{
    if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
        fail("Only POST requests are allowed.", 405);
    }
}


/*
 * Client (buyer) accounts live in their own table.
 * Staff / Owner / Admin / Cashier accounts stay in `users`.
 */
function ensure_clients_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(30) NULL,
            address VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
}

/*
 * One email can belong to only one account, across BOTH tables.
 */
function email_in_clients(PDO $pdo, string $email, int $exceptId = 0): bool
{
    try {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? AND id <> ? LIMIT 1");
        $stmt->execute([$email, $exceptId]);

        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        return false; // clients table does not exist yet
    }
}

function email_in_users(PDO $pdo, string $email, int $exceptId = 0): bool
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    $stmt->execute([$email, $exceptId]);

    return (bool)$stmt->fetch();
}

/*
 * Finds (or creates) the customer record of a buyer account.
 *   $linkColumn = "client_id"  -> account is in the clients table
 *   $linkColumn = "user_id"    -> older client account still stored in users
 * A customer who already exists with the same email is reused.
 */
function ensure_customer_for_account(PDO $pdo, string $linkColumn, int $accountId, string $name, string $email): ?int
{
    if (!in_array($linkColumn, ["client_id", "user_id"], true) || $accountId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM customers WHERE `$linkColumn` = ? LIMIT 1");
    $stmt->execute([$accountId]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int)$id;
    }

    if ($email !== "") {

        $m = $pdo->prepare("SELECT id FROM customers WHERE email = ? AND `$linkColumn` IS NULL LIMIT 1");
        $m->execute([$email]);
        $id = $m->fetchColumn();

        if ($id) {
            $pdo->prepare("UPDATE customers SET `$linkColumn` = ? WHERE id = ?")->execute([$accountId, $id]);
            return (int)$id;
        }
    }

    $pdo->prepare("INSERT INTO customers (name, email, `$linkColumn`) VALUES (?, ?, ?)")
        ->execute([$name !== "" ? $name : "Customer", $email !== "" ? $email : null, $accountId]);

    return (int)$pdo->lastInsertId();
}

/*
 * The customer for whoever is logged in right now (used when a buyer orders).
 */
function ensure_customer_for_session(PDO $pdo): ?int
{
    $isClientTable = ($_SESSION["user_type"] ?? "staff") === "client";

    return ensure_customer_for_account(
        $pdo,
        $isClientTable ? "client_id" : "user_id",
        (int)($_SESSION["user_id"] ?? 0),
        (string)($_SESSION["user_name"] ?? ""),
        (string)($_SESSION["user_email"] ?? "")
    );
}

/* ------------------------------------------------------------------
 * Database upgrade helpers
 * ------------------------------------------------------------------ */

function db_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);

    return (bool)$stmt->fetchColumn();
}

function db_column_info(PDO $pdo, string $table, string $column): ?array
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function db_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (db_table_exists($pdo, $table) && !db_column_info($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function db_make_nullable(PDO $pdo, string $table, string $column): void
{
    if (!db_table_exists($pdo, $table)) {
        return;
    }

    $info = db_column_info($pdo, $table, $column);

    if ($info && $info["Null"] !== "YES") {
        $pdo->exec("ALTER TABLE `$table` MODIFY `$column` {$info['Type']} NULL DEFAULT NULL");
    }
}

/* Adds an index on one column unless that column already starts an index. */
function db_add_index(PDO $pdo, string $table, string $column): void
{
    if (!db_table_exists($pdo, $table) || !db_column_info($pdo, $table, $column)) {
        return;
    }

    $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Column_name = ? AND Seq_in_index = 1");
    $stmt->execute([$column]);

    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_{$table}_{$column}` (`$column`)");
    }
}

/*
 * Makes sure the database has everything the separate client accounts,
 * logs and reports need, and speeds up the biggest tables with indexes.
 * Runs once per login session. Never call it inside a transaction.
 */
function ensure_account_split_schema(PDO $pdo): void
{
    if (!empty($_SESSION["account_schema_v3"])) {
        return;
    }

    $steps = [
        fn() => ensure_clients_table($pdo),
        fn() => db_add_column($pdo, "orders", "client_id", "INT UNSIGNED NULL"),
        fn() => db_make_nullable($pdo, "orders", "user_id"),
        fn() => db_make_nullable($pdo, "stock_logs", "user_id"),
        fn() => db_make_nullable($pdo, "login_logs", "user_id"),
        fn() => db_add_column($pdo, "login_logs", "client_id", "INT UNSIGNED NULL"),
        // keep the product name on each sold item, so a product can be deleted
        fn() => db_add_column($pdo, "order_items", "product_name", "VARCHAR(150) NULL"),
        fn() => db_make_nullable($pdo, "order_items", "product_id"),
        fn() => db_make_nullable($pdo, "stock_logs", "product_id"),
        fn() => $pdo->exec("
            UPDATE order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            SET oi.product_name = p.product_name
            WHERE oi.product_name IS NULL
        "),
        fn() => db_add_index($pdo, "orders", "client_id"),
        fn() => db_add_index($pdo, "orders", "user_id"),
        fn() => db_add_index($pdo, "orders", "customer_id"),
        fn() => db_add_index($pdo, "order_items", "order_id"),
        fn() => db_add_index($pdo, "order_items", "product_id"),
        fn() => db_add_index($pdo, "stock_logs", "product_id"),
        fn() => db_add_index($pdo, "login_logs", "user_id"),
    ];

    foreach ($steps as $step) {
        try {
            $step();
        } catch (Throwable $e) {
            // keep going; one failed step must not break the page
        }
    }

    try {
        if (db_table_exists($pdo, "orders")) {
            $dateColumn = find_date_column($pdo, "orders");

            if ($dateColumn) {
                db_add_index($pdo, "orders", $dateColumn);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    $_SESSION["account_schema_v3"] = true;
}
