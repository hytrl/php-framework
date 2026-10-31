<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

class DatabaseException extends RuntimeException
{
}

final class DB
{
    private ?PDO $pdo = null;
    private int $affected = 0;
    private int $queryCount = 0;
    private string $lastQuery = '';
    private array $log = [];
    private int $transDepth = 0;
    private bool $transFailed = false;

    public function __construct(private array $cfg)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $c   = $this->cfg;
        $dsn = (string) ($c['dsn'] ?? '');
        if ($dsn === '') {
            $dsn = $this->buildDsn();
        }

        $options = ($c['options'] ?? []) + [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => (bool) ($c['persistent'] ?? false),
        ];

        try {
            $this->pdo = new PDO($dsn, $c['username'] ?? null, $c['password'] ?? null, $options);
        } catch (PDOException $e) {
            log_message('error', 'DB connection failed: ' . $e->getMessage());
            throw new DatabaseException('Unable to connect to the database: ' . $e->getMessage(), 0, $e);
        }

        if (!empty($c['init_command'])) {
            $this->pdo->exec((string) $c['init_command']);
        }

        return $this->pdo;
    }

    public function close(): void
    {
        $this->pdo = null;
    }

    private function buildDsn(): string
    {
        $c      = $this->cfg;
        $driver = strtolower((string) ($c['driver'] ?? 'mysql'));
        $host   = (string) ($c['hostname'] ?? '127.0.0.1');
        $name   = (string) ($c['database'] ?? '');

        return match ($driver) {
            'mysql', 'mariadb' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host, (int) ($c['port'] ?? 3306), $name, (string) ($c['charset'] ?? 'utf8mb4')
            ),
            'pgsql' => sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, (int) ($c['port'] ?? 5432), $name),
            'sqlite' => 'sqlite:' . $name,
            default => throw new DatabaseException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * @param array $binds  Konumsal: [1, 'a']  veya isimli: ['id' => 1]
     */
    public function query(string $sql, array $binds = []): DB_result
    {
        $pdo = $this->pdo();
        $this->lastQuery = $sql;
        $this->queryCount++;
        $start = microtime(true);

        try {
            if ($binds === []) {
                $stmt = $pdo->query($sql);
            } else {
                $stmt = $pdo->prepare($sql);
                foreach ($binds as $key => $value) {
                    $param = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
                    $stmt->bindValue($param, $value, match (true) {
                        is_int($value)  => PDO::PARAM_INT,
                        is_bool($value) => PDO::PARAM_BOOL,
                        $value === null => PDO::PARAM_NULL,
                        default         => PDO::PARAM_STR,
                    });
                }
                $stmt->execute();
            }
        } catch (PDOException $e) {
            if ($this->transDepth > 0) {
                $this->transFailed = true;
            }
            log_message('error', 'Query failed: ' . $e->getMessage() . ' | ' . $sql);
            throw new DatabaseException($e->getMessage() . ' [SQL: ' . $sql . ']', 0, $e);
        }

        $this->affected = $stmt->rowCount();

        if (ENVIRONMENT === 'development') {
            $this->log[] = ['sql' => $sql, 'time' => round(microtime(true) - $start, 5)];
        }

        return new DB_result($stmt);
    }

    public function get(string $sql, array $binds = []): DB_result
    {
        return $this->query($sql, $binds);
    }

    public function insert_id(?string $name = null): string
    {
        return (string) $this->pdo()->lastInsertId($name);
    }

    public function affected_rows(): int
    {
        return $this->affected;
    }

    public function last_query(): string
    {
        return $this->lastQuery;
    }

    public function query_count(): int
    {
        return $this->queryCount;
    }

    public function query_log(): array
    {
        return $this->log;
    }

    public function escape(mixed $value): string
    {
        return match (true) {
            is_int($value)                     => (string) $value,
            is_float($value)                   => (is_nan($value) || is_infinite($value)) ? 'NULL' : (string) $value,
            is_bool($value)                    => $value ? '1' : '0',
            $value === null                    => 'NULL',
            default                            => (string) $this->pdo()->quote((string) $value),
        };
    }

    public function escape_like(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    public function trans_begin(): bool
    {
        if ($this->transDepth === 0) {
            $this->pdo()->beginTransaction();
            $this->transFailed = false;
        }
        $this->transDepth++;
        return true;
    }

    public function trans_start(): bool
    {
        return $this->trans_begin();
    }

    public function trans_commit(): bool
    {
        return $this->transEnd(true);
    }

    public function trans_rollback(): bool
    {
        $this->transFailed = true;
        return $this->transEnd(false);
    }

    public function trans_complete(): bool
    {
        return $this->transEnd(!$this->transFailed);
    }

    public function trans_status(): bool
    {
        return !$this->transFailed;
    }

    public function transaction(callable $fn): mixed
    {
        $this->trans_begin();
        try {
            $result = $fn($this);
        } catch (Throwable $e) {
            $this->trans_rollback();
            throw $e;
        }
        $this->trans_complete();
        return $result;
    }

    private function transEnd(bool $commit): bool
    {
        if ($this->transDepth === 0) {
            return false;
        }
        $this->transDepth--;
        if ($this->transDepth > 0) {
            return !$this->transFailed;
        }
        if ($commit && !$this->transFailed) {
            $this->pdo()->commit();
            return true;
        }
        $this->pdo()->rollBack();
        return false;
    }
}

final class DB_result
{
    private ?array $rows = null;
    private ?array $objects = null;

    public function __construct(private PDOStatement $stmt)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function result_array(): array
    {
        return $this->rows ??= ($this->stmt->columnCount() > 0 ? $this->stmt->fetchAll(PDO::FETCH_ASSOC) : []);
    }

    /** @return array<int, object> */
    public function result(): array
    {
        return $this->objects ??= array_map(static fn (array $r): object => (object) $r, $this->result_array());
    }

    public function row(int $n = 0): ?object
    {
        $r = $this->result_array()[$n] ?? null;
        return $r === null ? null : (object) $r;
    }

    public function row_array(int $n = 0): ?array
    {
        return $this->result_array()[$n] ?? null;
    }

    public function scalar(): mixed
    {
        $r = $this->row_array();
        return $r === null ? null : reset($r);
    }

    public function num_rows(): int
    {
        return count($this->result_array());
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function iterate(): Generator
    {
        if ($this->rows !== null) {
            yield from $this->rows;
            return;
        }
        if ($this->stmt->columnCount() === 0) {
            return;
        }
        while (($row = $this->stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            yield $row;
        }
    }
}
