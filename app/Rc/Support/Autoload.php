<?php
declare(strict_types=1);
/**
 * Lightweight PSR-4-style loader for App\Rc\* only.
 * Does not replace existing procedural requires — strangler-compatible.
 * Version: 20261002.01
 */
final class RcAutoload
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, 'App\\Rc\\')) {
                return;
            }
            $rel = str_replace('\\', '/', substr($class, strlen('App\\Rc\\')));
            $base = dirname(__DIR__); // app/Rc
            $file = $base . '/' . $rel . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        });
    }
}
