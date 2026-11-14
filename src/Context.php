<?php
declare(strict_types=1);

namespace LimeColumns;

use LimeColumns\Column\Column;
use LimeColumns\Column\ColumnRegistry;
use LimeColumns\Contracts\ColumnType;
use LimeColumns\Contracts\ListScreen;
use LimeColumns\Storage\ColumnSet;

/** 「どの一覧画面に、どの列セットを適用するか」を束ねた実行コンテキスト。 */
final class Context
{
    public function __construct(
        public readonly ListScreen $screen,
        public readonly ColumnSet $set,
        private readonly ColumnRegistry $types,
    ) {
    }

    public function type(Column $column): ?ColumnType
    {
        return $this->types->get($column->type);
    }

    public function column(string $name): ?Column
    {
        return $this->set->column($name);
    }

    /** native / foreign（既存の列キーをそのまま使う）列か。 */
    public function usesExistingKey(Column $column): bool
    {
        return $column->type === 'foreign' || $this->type($column)?->nativeKey() !== null;
    }
}
