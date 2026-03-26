<?php
declare(strict_types=1);

function base_path(string $path = ''): string {
    $base = dirname(__DIR__, 2);
    return $path ? $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $base;
}

function env(string $key, ?string $default = null): ?string {
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function json_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
