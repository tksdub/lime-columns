<?php
declare(strict_types=1);

namespace LimeColumns\Column;

use LimeColumns\Contracts\ColumnType;
use LimeColumns\Contracts\ListScreen;
use LimeColumns\Formatter\Format;

/** 列タイプの共通実装。サブクラスは id/label/group と value() 程度を書けばよい。 */
abstract class AbstractColumnType implements ColumnType
{
    /** @return list<string> */
    protected function objectTypes(): array
    {
        return ['post'];
    }

    public function group(): string
    {
        return 'basic';
    }

    public function supports(ListScreen $screen): bool
    {
        return in_array($screen->objectType(), $this->objectTypes(), true);
    }

    public function nativeKey(): ?string
    {
        return null;
    }

    public function fields(): array
    {
        return [];
    }

    public function capabilities(): array
    {
        return ['sort' => false, 'search' => false, 'export' => true];
    }

    public function render(Column $column, int $id): string
    {
        $v = $this->value($column, $id);
        return $v === '' ? Format::EMPTY_HTML : esc_html($v);
    }

    public function value(Column $column, int $id): string
    {
        return '';
    }

    public function sortArgs(Column $column): ?array
    {
        return null;
    }

    public function searchMetaKeys(Column $column): array
    {
        return [];
    }

    public function searchClause(Column $column, string $term): ?string
    {
        return null;
    }

    /** @return array{sort:bool,search:bool,export:bool} */
    protected static function caps(bool $sort = false, bool $search = false, bool $export = true): array
    {
        return ['sort' => $sort, 'search' => $search, 'export' => $export];
    }
}
