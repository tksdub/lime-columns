<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

final class Comments extends NativePostColumn
{
    public function id(): string { return 'comments'; }
    public function label(): string { return __('Comments (WP default)', 'lime-columns'); }
    public function nativeKey(): ?string { return 'comments'; }
    protected function orderby(): ?string { return 'comment_count'; }
    public function group(): string { return 'wp'; }
}
