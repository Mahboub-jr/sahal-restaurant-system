<?php
/**
 * The single database connection for the application.
 *
 * Replaces db.php, conn.php, library/conn.php and the inline connection in
 * add-menu.php (AUDIT.md §1.5). Those files remain for now so the not-yet-
 * converted pages keep working; they will be removed once every page is on
 * this connection.
 *
 * Always PDO, always prepared statements. Never string-interpolate a value
 * into SQL — pass it as a parameter.
 */

declare(strict_types=1);

/**
 * Returns the shared PDO connection, opening it on first use.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            // Throw on error rather than returning false and continuing with
            // bad data — the failure mode behind several bugs in the audit.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements, not client-side string substitution.
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        // Never leak credentials or SQL to the browser.
        error_log('DB connection failed: ' . $e->getMessage());

        http_response_code(500);
        if (defined('APP_ENV') && APP_ENV === 'development') {
            exit('<h1>Database connection failed</h1><pre>'
                . htmlspecialchars($e->getMessage()) . '</pre>'
                . '<p>Check that MySQL is running in XAMPP and that the database <code>'
                . htmlspecialchars(DB_NAME) . '</code> exists.</p>');
        }
        exit('Service temporarily unavailable.');
    }

    return $pdo;
}

/* =====================================================================
 | Thin query helpers
 |=====================================================================
 | These exist so pages read as intent rather than as PDO boilerplate.
 | Every one of them uses bound parameters.
 */

/**
 * Run a statement and return it. Use for INSERT/UPDATE/DELETE.
 */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch all rows.
 */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/**
 * Fetch a single row, or null.
 */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch a single scalar value from the first column.
 */
function db_value(string $sql, array $params = [], $default = null)
{
    $value = db_run($sql, $params)->fetchColumn();
    return $value === false ? $default : $value;
}

/**
 * Id of the row just inserted.
 */
function db_last_id(): int
{
    return (int) db()->lastInsertId();
}

/**
 * Run a callback inside a transaction, rolling back on any exception.
 */
function db_transaction(callable $work)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
