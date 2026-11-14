<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

final class Author extends NativePostColumn
{
    public function id(): string { return 'author'; }
    public function label(): string { return __('Author (WP default)', 'lime-columns'); }
    public function nativeKey(): ?string { return 'author'; }
    protected function orderby(): ?string { return 'author'; }
    public function group(): string { return 'wp'; }
}
