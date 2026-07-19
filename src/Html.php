<?php

declare(strict_types=1);

namespace Doorbell;

final class Html
{
    public static function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Renders a view file with the given variables in scope and returns the
     * output. Keeps templates free of echo/return plumbing.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require __DIR__ . '/View/' . $template . '.php';

        return (string) ob_get_clean();
    }
}
