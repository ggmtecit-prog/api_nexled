<?php

// File-based response cache. Caveman: 5 functions, single-line, no abstractions.
// Toggle via env CACHE_ENABLED=0 to bypass entirely. Bump CACHE_VERSION to invalidate all.

const CACHE_VERSION = "v1";

function cacheEnabled() { return getenv("CACHE_ENABLED") !== "0"; }
function cacheDir() { return sys_get_temp_dir() . DIRECTORY_SEPARATOR . "api_nexled_cache"; }
function cachePath($key) { return cacheDir() . DIRECTORY_SEPARATOR . md5(CACHE_VERSION . ":" . $key) . ".cache"; }

function cacheGet($key) {
    if (!cacheEnabled()) return null;
    $p = cachePath($key);
    if (!is_file($p)) return null;
    $raw = @file_get_contents($p);
    if ($raw === false) return null;
    $d = @unserialize($raw);
    if (!is_array($d) || !isset($d["exp"], $d["val"]) || $d["exp"] < time()) { @unlink($p); return null; }
    return $d["val"];
}

function cacheSet($key, $val, $ttl = 3600) {
    if (!cacheEnabled()) return false;
    $dir = cacheDir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return @file_put_contents(cachePath($key), serialize(["exp" => time() + $ttl, "val" => $val])) !== false;
}

function cacheBustRequested() { return isset($_GET["cache"]) && $_GET["cache"] === "bust"; }

function cacheSendStatusHeader($status) { if (!headers_sent()) header("X-Cache: " . $status); }

function cacheRemember($key, $ttl, $fn) {
    $bust = cacheBustRequested();
    if ($bust) @unlink(cachePath($key));
    $v = $bust ? null : cacheGet($key);
    if ($v !== null) { cacheSendStatusHeader("HIT"); return $v; }
    cacheSendStatusHeader($bust ? "BUST" : "MISS");
    $v = $fn();
    cacheSet($key, $v, $ttl);
    return $v;
}
