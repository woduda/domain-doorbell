<?php

declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader for the Doorbell namespace.
 *
 * The project has no dependencies on purpose: it has to run on the cheapest
 * shared hosting a parked domain would ever be pointed at, where Composer is
 * often not available. If you do run `composer install`, its autoloader is
 * used instead.
 */

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Doorbell\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = __DIR__ . '/src/' . $relative . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}

return Doorbell\Config::fromFile(__DIR__ . '/config.php');
