<?php
declare(strict_types=1);
namespace App\Rc\Http;

final class Request
{
    public static function action(string $default = 'dashboard'): string
    {
        $a = $_GET['action'] ?? $_POST['action'] ?? $default;
        $a = is_string($a) ? trim($a) : $default;
        return $a !== '' ? $a : $default;
    }

    public static function tab(string $default = 'team'): string
    {
        $t = $_GET['tab'] ?? $default;
        $t = is_string($t) ? trim($t) : $default;
        return $t !== '' ? $t : $default;
    }

    public static function isPost(): bool
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }
}
