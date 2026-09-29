<?php

namespace App\Services;

class SensitiveText
{
    public static function cleanArray(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        foreach ($data as $key => $value) {
            $data[$key] = is_array($value) ? self::cleanArray($value) : (is_string($value) ? self::clean($value) : $value);
        }

        return $data;
    }

    public static function clean(?string $text, int $limit = 65536): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = substr($text, 0, $limit);
        $text = preg_replace('/\b(Bearer\s+)[A-Za-z0-9._~+\/-]+/i', '$1[REDACTED]', $text);
        $text = preg_replace('/\b([A-Z][A-Z0-9_]*(?:TOKEN|PASSWORD|SECRET|API_KEY|PRIVATE_KEY)[A-Z0-9_]*\s*[=:]\s*)[^\s]+/i', '$1[REDACTED]', $text);
        $text = preg_replace('/\b(sk-(?:proj-)?[A-Za-z0-9_-]{12,})\b/', '[REDACTED]', $text);

        return $text;
    }
}
