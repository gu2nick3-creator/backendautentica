<?php
declare(strict_types=1);
namespace App\Config;

class Env {
    public static function load(string $path): void {
        if (!is_file($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim(trim($v), "\"'");
            $_SERVER[trim($k)] = trim(trim($v), "\"'");
        }
    }
}
