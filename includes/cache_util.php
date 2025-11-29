<?php

/**
 * Path to the cache directory.
 */
define('CACHE_DIR', __DIR__ . '/../cache/');

/**
 * Retrieves data from the cache.
 *
 * @param string $key The unique key for the cache entry.
 * @param int $expiration_time The maximum age of the cache in seconds.
 * @return mixed|null Cached data if valid, otherwise null.
 */
function get_cache(string $key, int $expiration_time = 3600)
{
    $file_path = CACHE_DIR . md5($key) . '.cache';

    if (!file_exists($file_path)) {
        return null;
    }

    $file_time = filemtime($file_path);
    if (time() - $file_time > $expiration_time) {
        // Cache expired
        unlink($file_path); // Delete expired cache file
        return null;
    }

    $data = file_get_contents($file_path);
    return json_decode($data, true);
}

/**
 * Stores data in the cache.
 *
 * @param string $key The unique key for the cache entry.
 * @param mixed $data The data to store.
 * @return bool True on success, false on failure.
 */
function set_cache(string $key, $data): bool
{
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0777, true);
    }
    $file_path = CACHE_DIR . md5($key) . '.cache';
    return (bool) file_put_contents($file_path, json_encode($data));
}

/**
 * Invalidates (deletes) a specific cache entry.
 *
 * @param string $key The unique key of the cache entry to invalidate.
 * @return bool True on success, false if the file didn't exist or couldn't be deleted.
 */
function invalidate_cache(string $key): bool
{
    $file_path = CACHE_DIR . md5($key) . '.cache';
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}

?>