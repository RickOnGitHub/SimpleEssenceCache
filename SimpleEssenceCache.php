<?php

/**
 * SimpleEssenceCache - Simple File-Based Caching Class
 *
 * @author   Rick van Oirschot
 * @license  MIT
 * @link     https://rickvanoirschot.nl
 *
 * MIT License
 *
 * Copyright (c) 2025 Rick van Oirschot
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace SimpleEssence\Cache;

class SimpleEssenceCache
{
    /**
     * @var string
     */
    private $cachePath;

    /**
     * Constructor.
     *
     * @param string $cachePath Directory to store cache files
     */
    public function __construct(string $cachePath)
    {
        $this->cachePath = rtrim($cachePath, DIRECTORY_SEPARATOR);

        if (!is_dir($this->cachePath)) {
            if (!mkdir($this->cachePath, 0755, true) && !is_dir($this->cachePath)) {
                throw new \RuntimeException("Unable to create cache directory at: {$this->cachePath}");
            }
        }
    }

    /**
     * Save data to cache with a custom expiration.
     *
     * @param string $key               The cache key
     * @param mixed  $data              The data to store
     * @param int    $durationInMinutes Time to live in minutes
     * @return void
     */
    public function set($key, $data, $durationInMinutes): void
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException('Cache key must be a string.');
        }

        if (!is_numeric($durationInMinutes) || $durationInMinutes <= 0) {
            throw new \InvalidArgumentException('Duration must be a positive number (in minutes).');
        }

        $filePath = $this->getFilePath($key);
        $expiresAt = time() + ((int) $durationInMinutes * 60);

        $payload = [
            'expires_at' => $expiresAt,
            'data' => $this->normalizeData($data),
        ];

        file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get cached data by key.
     *
     * @param string $key
     * @return mixed|null
     */
    public function get($key): mixed
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);

        if (!is_array($decoded) || time() > ($decoded['expires_at'] ?? 0)) {
            $this->delete($key);
            return null;
        }

        return $decoded['data'] ?? null;
    }

    /**
     * Check if cache exists and is valid.
     *
     * @param string $key
     * @return bool
     */
    public function has($key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Delete a cache key.
     *
     * @param string $key
     * @return void
     */
    public function delete($key): void
    {
        $filePath = $this->getFilePath($key);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Delete only expired cache files.
     * 
     * @return void
     */
    public function clearExpiredCaches(): void
    {
        $files = glob($this->cachePath . DIRECTORY_SEPARATOR . '*.cache');

        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);

            if (!is_array($decoded) || time() > ($decoded['expires_at'] ?? 0)) {
                unlink($file);
            }
        }
    }

    /**
     * Delete all cache files, regardless of expiration.
     * 
     * @return void
     */
    public function clearAllCaches(): void
    {
        $files = glob($this->cachePath . DIRECTORY_SEPARATOR . '*.cache');

        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Generate file path from cache key.
     *
     * @param string $key
     * @return string
     */
    private function getFilePath($key): string
    {
        // Make sure the key isset and is a string
        if (empty($key) || !is_string($key)) {
            throw new \InvalidArgumentException('Cache key must be a non-empty string.');
        }

        $hashedKey = hash('sha256', $key);
        return $this->cachePath . DIRECTORY_SEPARATOR . $hashedKey . '.cache';
    }

    /**
     * Normalize input data.
     *
     * @param mixed $data
     * @return mixed
     */
    private function normalizeData($data): mixed
    {
        // If already a JSON string, decode it to array/object
        if (is_string($data) && $this->isJson($data)) {
            return json_decode($data, true);
        }

        return $data;
    }

    /**
     * Determine if a string is valid JSON.
     *
     * @param string $string
     * @return bool
     */
    private function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
