<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class PresensiApiLog
{
    public static function channel(): \Psr\Log\LoggerInterface
    {
        return Log::channel('presensi_api');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function request(string $operation, array $context = []): void
    {
        self::channel()->info('Presensi API request', array_merge([
            'operation' => $operation,
            'api_url' => config('presensi.api_url'),
        ], $context));
    }

    public static function connectionFailed(string $operation, \Throwable $e): void
    {
        $curl = function_exists('curl_version') ? curl_version() : [];

        self::channel()->error('Presensi API connection failed', [
            'operation' => $operation,
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'api_url' => config('presensi.api_url'),
            'trace' => self::traceHead($e),
            'php_version' => PHP_VERSION,
            'openssl' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null,
            'curl_version' => $curl['version'] ?? null,
            'ssl_version' => $curl['ssl_version'] ?? null,
        ]);
    }

    public static function badHttp(string $operation, Response $response): void
    {
        $body = $response->body();

        self::channel()->warning('Presensi API HTTP error', [
            'operation' => $operation,
            'status' => $response->status(),
            'reason' => $response->reason(),
            'body_preview' => mb_substr($body, 0, 4000),
            'body_length' => strlen($body),
            'api_url' => config('presensi.api_url'),
        ]);
    }

    public static function responseOk(string $operation, Response $response, mixed $parsedJson = null): void
    {
        self::channel()->debug('Presensi API response OK', [
            'operation' => $operation,
            'status' => $response->status(),
            'parsed_type' => get_debug_type($parsedJson),
        ]);
    }

    private static function traceHead(\Throwable $e): string
    {
        $lines = explode("\n", $e->getTraceAsString());

        return implode("\n", array_slice($lines, 0, 12));
    }
}
