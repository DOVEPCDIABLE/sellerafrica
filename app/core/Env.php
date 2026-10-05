<?php
/**
 * File Folder Path: /app/core
 * File Path: /app/core/Env.php
 * Designed by Daniel Pybexai Framework
 * ==============================================================================
 * CORE ENVIRONMENT LOADER
 * Summary: A lightweight, dependency-free class to parse .env files and 
 * populate PHP's $_ENV and $_SERVER superglobals.
 */

declare(strict_types=1);

namespace App\Core;

class Env
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    /**
     * Load environment variables from a given file path.
     *
     * @param string $path The absolute path to the .env file.
     * @throws \RuntimeException If the file cannot be read.
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            // Silently fail if .env doesn't exist to allow production servers
            // to rely entirely on actual server environment variables.
            return;
        }

        if (!is_readable($path)) {
            throw new \RuntimeException(sprintf('Environment file "%s" is not readable.', $path));
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException(sprintf('Could not read environment file "%s".', $path));
        }

        foreach ($lines as $line) {
            // Trim whitespace from the line
            $line = trim($line);

            // Skip empty lines and comments (lines starting with #)
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Split the line into Name and Value by the first equals sign
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                
                $name = trim($name);
                $value = trim($value);

                // Strip surrounding quotes from the value if present (both single and double)
                if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                // Inject into environment arrays if not already set by the server environment
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    // putenv is optional, but useful for older libraries. We primarily rely on $_ENV.
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}
