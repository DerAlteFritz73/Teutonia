<?php

namespace App\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tests')]
#[IsGranted('ROLE_ADMIN')]
class TestRunnerController extends AbstractController
{
    #[Route('', name: 'admin_tests')]
    public function index(Connection $db): Response
    {
        try {
            $runs = $db->fetchAllAssociative(
                'SELECT * FROM test_runs ORDER BY id DESC LIMIT 30'
            );
        } catch (\Exception) {
            $runs = [];
        }

        return $this->render('admin/tests/index.html.twig', [
            'runs' => $runs,
        ]);
    }

    #[Route('/run', name: 'admin_tests_run')]
    public function run(): Response
    {
        return $this->render('admin/tests/run.html.twig', [
            'streamUrl' => $this->generateUrl('admin_tests_stream'),
            'baseUrl'   => $this->generateUrl('admin_tests'),
        ]);
    }

    #[Route('/stream', name: 'admin_tests_stream')]
    public function streamOutput(Connection $db): StreamedResponse
    {
        return new StreamedResponse(function () use ($db) {
            set_time_limit(0);

            // Capture the highest run ID before starting so we can find the new one
            try {
                $beforeId = (int) $db->fetchOne('SELECT COALESCE(MAX(id), 0) FROM test_runs');
            } catch (\Exception) {
                $beforeId = 0;
            }

            $projectDir = dirname(__DIR__, 3);
            $cmd = sprintf(
                'cd %s && php vendor/bin/phpunit --colors=never 2>&1',
                escapeshellarg($projectDir)
            );

            $proc = popen($cmd, 'r');
            if ($proc === false) {
                echo 'data: ' . json_encode('[ERROR] Could not start phpunit process') . "\n\n";
                flush();
                return;
            }

            while (!feof($proc)) {
                $line = fgets($proc, 8192);
                if ($line !== false && $line !== '') {
                    echo 'data: ' . json_encode(rtrim($line, "\r\n")) . "\n\n";
                    ob_flush();
                    flush();
                }
            }
            pclose($proc);

            // Find the run record that was just written by TestResultsExtension
            $runId = null;
            try {
                $runId = $db->fetchOne(
                    'SELECT id FROM test_runs WHERE id > ? ORDER BY id ASC LIMIT 1',
                    [$beforeId]
                );
            } catch (\Exception) {
            }

            echo "event: done\n";
            echo 'data: ' . json_encode(['runId' => $runId ? (int) $runId : null]) . "\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    #[Route('/{id}', name: 'admin_tests_show', requirements: ['id' => '\d+'])]
    public function show(int $id, Connection $db): Response
    {
        try {
            $run = $db->fetchAssociative('SELECT * FROM test_runs WHERE id = ?', [$id]);
            $results = $db->fetchAllAssociative(
                'SELECT * FROM test_results WHERE run_id = ? ORDER BY id',
                [$id]
            );
        } catch (\Exception) {
            $run = null;
            $results = [];
        }

        if (!$run) {
            throw $this->createNotFoundException('Test run not found');
        }

        return $this->render('admin/tests/show.html.twig', [
            'run'     => $run,
            'results' => $results,
        ]);
    }
}
