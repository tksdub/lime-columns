<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

final class Title extends NativePostColumn
{
    public function id(): string { return 'title'; }
    public function label(): string { return __('Title', 'lime-columns'); }
    public function nativeKey(): ?string { return 'title'; }
    protected function orderby(): ?string { return 'title'; }
    public function group(): string { return 'wp'; }
}
