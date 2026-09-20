<?php
declare(strict_types=1);

namespace App;

final class Http
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string}
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 60): array
    {
        if (function_exists('curl_init')) {
            return self::curl($method, $url, $headers, $body, $timeout);
        }
        return self::streams($method, $url, $headers, $body, $timeout);
    }

    private static function curl(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $ch = curl_init($url);
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_USERAGENT => 'ChatbotPlatform/1.0 (+PHP)',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new HttpException('Request failed: ' . $error);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string)$response];
    }

    private static function streams(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $headerLines = '';
        foreach ($headers as $name => $value) {
            $headerLines .= $name . ': ' . $value . "\r\n";
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headerLines,
                'content' => $body ?? '',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'follow_location' => 1,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new HttpException('Request failed: outbound HTTP is blocked on this server.');
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int)$m[1];
            }
        }
        return ['status' => $status, 'body' => $response];
    }
}
