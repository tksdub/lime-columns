<?php
declare(strict_types=1);

namespace LimeColumns\Integrations\Acf;

use LimeColumns\Column\ColumnRegistry;
use LimeColumns\Contracts\Integration;

final class AcfIntegration implements Integration
{
    public function id(): string { return 'acf'; }

    public function available(): bool
    {
        return function_exists('acf_get_field') && function_exists('get_field');
    }

    public function register(ColumnRegistry $types): void
    {
        $types->add(new AcfFieldColumn('post'))->add(new AcfFieldColumn('user'));
    }
}
