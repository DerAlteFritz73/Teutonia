<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/prod-sync')]
#[IsGranted('ROLE_ADMIN')]
class ProdSyncController extends AbstractController
{
    /**
     * Asset directories mounted (writable) into the php container, mirrored
     * one-to-one between prod and dev via the shared compose.yaml volumes.
     */
    private const ASSET_PATHS = [
        '/var/www/html/public/images/posts',
        '/var/www/html/public/images/styles',
        '/var/assets',
        '/var/www/html/public/pdfs',
    ];

    public function __construct(
        #[Autowire('%env(HETZNER_SSH_HOST)%')] private string $sshHost,
        #[Autowire('%env(HETZNER_SSH_USER)%')] private string $sshUser,
        #[Autowire('%env(HETZNER_SSH_KEY)%')]  private string $sshKey,
        #[Autowire('%env(DATABASE_URL)%')]     private string $dbUrl,
    ) {}

    #[Route('/copy', name: 'admin_prod_sync_copy', methods: ['POST'])]
    public function copy(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('prod_sync_copy', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Ungültiges CSRF-Token – bitte Seite neu laden und erneut versuchen.'], Response::HTTP_FORBIDDEN);
        }

        set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $steps = [];

        // 1) Database: dump prod and import into the local dev database.
        try {
            $dump = $this->dumpProdDatabase();
            $this->importDatabase($dump);
            $steps[] = ['label' => 'Datenbank', 'ok' => true, 'detail' => sprintf('%s übertragen und importiert', $this->humanBytes(strlen($dump)))];
        } catch (\Throwable $e) {
            $steps[] = ['label' => 'Datenbank', 'ok' => false, 'detail' => $e->getMessage()];
            return new JsonResponse(['success' => false, 'steps' => $steps], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // 2) Assets: stream each volume from prod and extract it in place.
        $allOk = true;
        foreach (self::ASSET_PATHS as $path) {
            try {
                $this->copyAssetPath($path);
                $steps[] = ['label' => 'Assets: ' . basename($path), 'ok' => true, 'detail' => 'kopiert'];
            } catch (\Throwable $e) {
                $allOk = false;
                $steps[] = ['label' => 'Assets: ' . basename($path), 'ok' => false, 'detail' => $e->getMessage()];
            }
        }

        return new JsonResponse(['success' => $allOk, 'steps' => $steps], $allOk ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function dumpProdDatabase(): string
    {
        // Run mariadb-dump inside the prod database container. The root password
        // and database name come from the container's own environment.
        $remoteScript = 'exec mariadb-dump --no-tablespaces --single-transaction --routines '
            . '--add-drop-table -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"';
        $remoteCmd = "docker exec teutonia-database-1 sh -c '" . $remoteScript . "'";

        [$out, $err, $code] = $this->run($this->ssh() . ' ' . escapeshellarg($remoteCmd));
        if ($code !== 0 || $out === '') {
            throw new \RuntimeException('Dump fehlgeschlagen: ' . ($err !== '' ? $err : 'leere Ausgabe'));
        }

        return $out;
    }

    private function importDatabase(string $dump): void
    {
        $p = parse_url($this->dbUrl);
        if ($p === false || !isset($p['host'])) {
            throw new \RuntimeException('DATABASE_URL konnte nicht gelesen werden');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $p['host'],
            $p['port'] ?? 3306,
            ltrim($p['path'] ?? '', '/'),
        );

        $pdo = new \PDO($dsn, $p['user'] ?? null, isset($p['pass']) ? rawurldecode($p['pass']) : null, [
            \PDO::ATTR_ERRMODE                => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec($dump);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function copyAssetPath(string $path): void
    {
        $arg       = escapeshellarg($path);
        $remoteCmd = "docker exec teutonia-php-1 tar -czf - -C $path .";
        // Stream the archive over SSH and unpack it into the same mounted path.
        $cmd = $this->ssh() . ' ' . escapeshellarg($remoteCmd) . ' | tar -xzf - -C ' . $arg;

        [, $err, $code] = $this->run($cmd);
        if ($code !== 0) {
            throw new \RuntimeException($err !== '' ? $err : 'tar/ssh exit ' . $code);
        }
    }

    private function ssh(): string
    {
        return sprintf(
            'ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15 -o UserKnownHostsFile=/tmp/prod_sync_known_hosts -i %s %s@%s',
            escapeshellarg($this->sshKey),
            escapeshellarg($this->sshUser),
            escapeshellarg($this->sshHost),
        );
    }

    /**
     * @return array{0:string,1:string,2:int} [stdout, stderr, exitCode]
     */
    private function run(string $cmd): array
    {
        $process = proc_open(['/bin/sh', '-c', $cmd], [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Prozess konnte nicht gestartet werden');
        }

        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [$out ?: '', trim($err ?: ''), $code];
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }
}
