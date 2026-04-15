<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/hetzner')]
class HetznerTerminalController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(HETZNER_SSH_HOST)%')] private string $sshHost,
        #[Autowire('%env(HETZNER_SSH_USER)%')] private string $sshUser,
        #[Autowire('%env(HETZNER_SSH_KEY)%')]  private string $sshKey,
    ) {}

    #[Route('/terminal/connect', name: 'admin_hetzner_terminal_connect', methods: ['POST'])]
    public function connect(): JsonResponse
    {
        $id = bin2hex(random_bytes(12));
        file_put_contents($this->inputFile($id), '');

        return new JsonResponse(['id' => $id]);
    }

    #[Route('/terminal/stream/{id}', name: 'admin_hetzner_terminal_stream')]
    public function sshStream(string $id): StreamedResponse
    {
        return new StreamedResponse(function () use ($id) {
            set_time_limit(0);
            ignore_user_abort(true);

            $inputFile = $this->inputFile($id);

            $cmd = sprintf(
                'ssh -tt -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 -o UserKnownHostsFile=/tmp/hetzner_known_hosts -i %s %s@%s',
                escapeshellarg($this->sshKey),
                escapeshellarg($this->sshUser),
                escapeshellarg($this->sshHost),
            );

            $process = proc_open($cmd, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);

            if (!is_resource($process)) {
                echo "event: error\ndata: SSH-Prozess konnte nicht gestartet werden\n\n";
                ob_flush();
                flush();
                return;
            }

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $lastPos = 0;

            while (!connection_aborted()) {
                // Forward any pending user input to SSH stdin
                clearstatcache(true, $inputFile);
                $size = @filesize($inputFile);
                if ($size !== false && $size > $lastPos) {
                    $f = @fopen($inputFile, 'rb');
                    if ($f) {
                        fseek($f, $lastPos);
                        $input = fread($f, $size - $lastPos);
                        fclose($f);
                        if ($input !== false && strlen($input) > 0) {
                            fwrite($pipes[0], $input);
                            $lastPos = $size;
                        }
                    }
                }

                // Read SSH stdout + stderr (merged on PTY)
                $out = fread($pipes[1], 4096);
                if ($out !== false && strlen($out) > 0) {
                    echo 'data: ' . base64_encode($out) . "\n\n";
                    ob_flush();
                    flush();
                }

                $err = fread($pipes[2], 4096);
                if ($err !== false && strlen($err) > 0) {
                    echo 'data: ' . base64_encode($err) . "\n\n";
                    ob_flush();
                    flush();
                }

                $status = proc_get_status($process);
                if (!$status['running']) {
                    echo "event: close\ndata: disconnected\n\n";
                    ob_flush();
                    flush();
                    break;
                }

                usleep(20000);
            }

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_terminate($process);
            proc_close($process);
            @unlink($inputFile);
        }, 200, [
            'Content-Type'     => 'text/event-stream',
            'Cache-Control'    => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    #[Route('/terminal/input/{id}', name: 'admin_hetzner_terminal_input', methods: ['POST'])]
    public function input(string $id, Request $request): JsonResponse
    {
        $data = $request->getContent();
        if (strlen($data) > 0) {
            file_put_contents($this->inputFile($id), $data, FILE_APPEND | LOCK_EX);
        }

        return new JsonResponse(['ok' => true]);
    }

    private function inputFile(string $id): string
    {
        return sys_get_temp_dir() . '/ssh_' . preg_replace('/[^a-f0-9]/', '', $id) . '_in';
    }
}
