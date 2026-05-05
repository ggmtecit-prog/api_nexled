<?php

// Caveman structured logger. JSON line per entry. Daily file rotation.
// Disable globally: LOG_ENABLED=0. Override location: LOG_DIR=/path/to/logs.

function logEnabled(): bool { return getenv("LOG_ENABLED") !== "0"; }

function logDir(): string {
    $env = (string) getenv("LOG_DIR");
    if ($env !== "") return $env;
    return dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . "logs";
}

function logPath(): string { return logDir() . DIRECTORY_SEPARATOR . gmdate("Y-m-d") . ".log"; }

function logWrite(string $level, string $endpoint, string $message, array $context = []): void {
    if (!logEnabled()) return;
    $dir = logDir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $line = json_encode([
        "ts" => gmdate("c"),
        "level" => $level,
        "endpoint" => $endpoint,
        "message" => $message,
        "context" => $context,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) return;
    @file_put_contents(logPath(), $line . PHP_EOL, FILE_APPEND);
}

function logError(string $endpoint, string $message, array $context = []): void { logWrite("error", $endpoint, $message, $context); }
function logWarn(string $endpoint, string $message, array $context = []): void { logWrite("warn", $endpoint, $message, $context); }
function logInfo(string $endpoint, string $message, array $context = []): void { logWrite("info", $endpoint, $message, $context); }
