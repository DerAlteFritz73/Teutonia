<?php

namespace App\Tests\Extension;

class TestResultsStorage
{
    private \PDO $pdo;
    private int $runId;

    /** @var array<string, array{status: string, message: string}> */
    private array $pending = [];

    public function __construct()
    {
        $this->pdo = $this->connect();
        $this->ensureTables();
    }

    private function connect(): \PDO
    {
        $url = $this->resolveDatabaseUrl();

        // mysql://user:pass@host:port/dbname?options
        $parsed = parse_url($url);

        $dbname = ltrim($parsed['path'] ?? '', '/');
        $dbname = explode('?', $dbname)[0];

        // Always write to the main (non-test) database so results survive fixture purges
        $dbname = preg_replace('/_test$/', '', $dbname);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $parsed['host'] ?? '127.0.0.1',
            $parsed['port'] ?? 3306,
            $dbname,
        );

        return new \PDO($dsn, $parsed['user'] ?? null, $parsed['pass'] ?? null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function resolveDatabaseUrl(): string
    {
        // Prefer the runtime environment variable — correct in Docker containers
        // where the .env file on disk may contain a wrong placeholder host.
        $env = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if ($env) {
            return $env;
        }

        // Fall back to .env files for CLI runs outside Docker
        $root    = dirname(__DIR__, 2);
        $sources = [$root . '/.env.local', $root . '/.env'];

        foreach ($sources as $file) {
            if (!file_exists($file)) {
                continue;
            }
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with($line, 'DATABASE_URL=')) {
                    $value = substr($line, strlen('DATABASE_URL='));
                    return trim($value, '"\'');
                }
            }
        }

        throw new \RuntimeException('DATABASE_URL not found — cannot store test results.');
    }

    private function ensureTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS test_runs (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                started_at  DATETIME NOT NULL,
                finished_at DATETIME,
                total       INT DEFAULT 0,
                passed      INT DEFAULT 0,
                failed      INT DEFAULT 0,
                errored     INT DEFAULT 0,
                skipped     INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS test_results (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                run_id      INT NOT NULL,
                test_class  VARCHAR(255),
                test_name   VARCHAR(255),
                status      ENUM("passed","failed","error","skipped") NOT NULL,
                duration_ms INT,
                message     TEXT,
                INDEX idx_run (run_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function startRun(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO test_runs (started_at) VALUES (NOW())');
        $stmt->execute();
        $this->runId = (int) $this->pdo->lastInsertId();
    }

    public function setTestStatus(string $testId, string $status, string $message = ''): void
    {
        $this->pending[$testId] = ['status' => $status, 'message' => $message];
    }

    public function recordFinished(string $testId, string $className, string $testName, int $durationMs): void
    {
        $info = $this->pending[$testId] ?? ['status' => 'passed', 'message' => ''];
        unset($this->pending[$testId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO test_results (run_id, test_class, test_name, status, duration_ms, message)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$this->runId, $className, $testName, $info['status'], $durationMs, $info['message']]);
    }

    public function finishRun(): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE test_runs SET
                finished_at = NOW(),
                total   = (SELECT COUNT(*)                              FROM test_results WHERE run_id = :id),
                passed  = (SELECT COUNT(*) FROM test_results WHERE run_id = :id AND status = "passed"),
                failed  = (SELECT COUNT(*) FROM test_results WHERE run_id = :id AND status = "failed"),
                errored = (SELECT COUNT(*) FROM test_results WHERE run_id = :id AND status = "error"),
                skipped = (SELECT COUNT(*) FROM test_results WHERE run_id = :id AND status = "skipped")
            WHERE id = :id
        ');
        $stmt->execute([':id' => $this->runId]);
    }

    public function getRunId(): int
    {
        return $this->runId;
    }
}
