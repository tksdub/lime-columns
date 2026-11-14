<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

use LimeColumns\Column\Column;
use LimeColumns\Support\SearchClause;

final class Author extends NativePostColumn
{
    public function id(): string { return 'author'; }
    public function label(): string { return __('Author (WP default)', 'lime-columns'); }
    public function nativeKey(): ?string { return 'author'; }
    protected function orderby(): ?string { return 'author'; }
    public function group(): string { return 'wp'; }
    public function capabilities(): array { return self::caps(sort: true, search: true); }
    public function searchClause(Column $column, string $term): ?string { return SearchClause::author($term); }
}
