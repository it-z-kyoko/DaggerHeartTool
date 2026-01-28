<?php
// /Database/Database.php
// Lightweight SQLite PDO wrapper for Daggerheart.db

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct(string $dbFilePath)
    {
        if (!file_exists($dbFilePath)) {
            throw new RuntimeException("Datenbankdatei nicht gefunden: {$dbFilePath}");
        }

        $this->pdo = new PDO("sqlite:" . $dbFilePath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Helpful pragmas for SQLite (optional but recommended)
        $this->pdo->exec("PRAGMA foreign_keys = ON;");
        $this->pdo->exec("PRAGMA journal_mode = WAL;");
        $this->pdo->exec("PRAGMA busy_timeout = 5000;");
    }

    /**
     * Get the shared instance (first call must provide the path).
     */
    public static function getInstance(?string $dbFilePath = null): Database
    {
        if (self::$instance === null) {
            if ($dbFilePath === null) {
                throw new InvalidArgumentException("Erster Aufruf von getInstance() benötigt den DB-Pfad.");
            }
            self::$instance = new Database($dbFilePath);
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** Prepare + execute and return the PDOStatement */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch all rows */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Fetch single row or null */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Execute a statement (INSERT/UPDATE/DELETE). Returns affected rows */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    // Transaction helpers
    public function begin(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollBack(): void { $this->pdo->rollBack(); }

    // Prevent cloning/unserialization
    private function __clone() {}
    public function __wakeup() { throw new RuntimeException("Cannot unserialize Database singleton"); }
}
