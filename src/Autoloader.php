<?php
declare(strict_types=1);

namespace LimeColumns;

/** composer 不要の最小 PSR-4 オートローダ（実行時依存ゼロにするため）。 */
final class Autoloader
{
    public static function register(string $baseDir): void
    {
        $baseDir = rtrim($baseDir, '/\\') . '/';
        spl_autoload_register(static function (string $class) use ($baseDir): void {
            $prefix = __NAMESPACE__ . '\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
