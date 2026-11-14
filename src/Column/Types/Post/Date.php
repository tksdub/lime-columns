<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

final class Date extends NativePostColumn
{
    public function id(): string { return 'date'; }
    public function label(): string { return __('Date (WP default)', 'lime-columns'); }
    public function nativeKey(): ?string { return 'date'; }
    protected function orderby(): ?string { return 'date'; }
    public function group(): string { return 'wp'; }
}
