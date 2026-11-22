<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

class SystemServiceHealth
{
    public function reverb(): string
    {
        if (config('broadcasting.default') !== 'reverb') {
            return 'Disabled';
        }

        $config = config('broadcasting.connections.reverb');
        $host = $config['options']['host'] ?? null;
        $key = $config['key'] ?? null;
        if (! $host || ! $key) {
            return 'Not configured';
        }

        $secure = ($config['options']['scheme'] ?? 'https') === 'https';
        $port = (int) ($config['options']['port'] ?? ($secure ? 443 : 80));
        $socket = null;
        try {
            $socket = @stream_socket_client(($secure ? 'tls://' : 'tcp://').$host.':'.$port, $errno, $error, 2);
            if (! $socket) {
                return 'Offline';
            }
            stream_set_timeout($socket, 2);
            $nonce = base64_encode(random_bytes(16));
            $origin = rtrim((string) config('app.url'), '/');
            $target = '/app/'.rawurlencode($key).'?protocol=7&client=js&version=8.4.0';
            fwrite($socket, "GET {$target} HTTP/1.1\r\nHost: {$host}:{$port}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$nonce}\r\nSec-WebSocket-Version: 13\r\nOrigin: {$origin}\r\n\r\n");
            $deadline = microtime(true) + 2;
            $headers = '';
            while (! str_contains($headers, "\r\n\r\n") && strlen($headers) < 8192 && microtime(true) < $deadline) {
                $line = fgets($socket, 1024);
                if ($line === false) {
                    break;
                }
                $headers .= $line;
            }
            $accept = base64_encode(sha1($nonce.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            return preg_match('/^HTTP\/1\.[01] 101\b/', $headers)
                && preg_match('/^Sec-WebSocket-Accept:\s*'.preg_quote($accept, '/').'\s*$/mi', $headers)
                ? 'Online' : 'Not verified';
        } catch (\Throwable) {
            return 'Offline';
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    public function email(): string
    {
        $mailer = config('mail.default');
        $driver = config("mail.mailers.{$mailer}.transport");
        if (in_array($driver, ['log', 'array'], true)) {
            return 'Disabled';
        }
        if ($driver === 'brevo') {
            return $this->brevoEmail();
        }
        if ($driver !== 'smtp') {
            return 'Not verified';
        }

        $transport = null;
        try {
            $transport = Mail::mailer($mailer)->getSymfonyTransport();
            if (! $transport instanceof SmtpTransport) {
                return 'Not verified';
            }
            $transport->getStream()->setTimeout(3);
            $transport->start();
            return 'Online';
        } catch (\Throwable) {
            return 'Offline';
        } finally {
            if ($transport instanceof SmtpTransport) {
                try {
                    $transport->stop();
                } catch (\Throwable) {}
            }
        }
    }

    /**
     * Verify Brevo API authentication without sending a message.
     * This is connectivity only; it cannot prove successful email delivery.
     */
    private function brevoEmail(): string
    {
        $key = trim((string) config('services.brevo.key', ''));
        if ($key === '') {
            return 'Not verified';
        }

        try {
            // Fixed official account endpoint: never call the configured email-send URL.
            $response = Http::acceptJson()
                ->withHeaders(['api-key' => $key])
                ->connectTimeout(2)
                ->timeout(3)
                ->get('https://api.brevo.com/v3/account');

            if ($response->ok()) {
                return 'Online';
            }

            // Account lookup permission or rate limits may differ from sending.
            return in_array($response->status(), [403, 429], true)
                ? 'Not verified' : 'Offline';
        } catch (\Throwable) {
            return 'Offline';
        }
    }

    public function scheduler(): string
    {
        try {
            $heartbeat = DB::table('system_service_heartbeats')->where('service', 'scheduler')->value('last_seen_at');
            return self::heartbeatStatus($heartbeat);
        } catch (\Throwable) {
            return 'Not verified';
        }
    }

    public static function heartbeatStatus(?int $heartbeat): string
    {
        if ($heartbeat === null) {
            return 'Not verified';
        }
        $age = now()->timestamp - $heartbeat;
        return $age >= -30 && $age <= 180 ? 'Online' : 'Offline';
    }
}
