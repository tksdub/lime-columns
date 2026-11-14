<?php
declare(strict_types=1);

namespace LimeColumns\Column;

use LimeColumns\Contracts\ColumnType;
use LimeColumns\Contracts\ListScreen;

final class ColumnRegistry
{
    /** @var array<string, ColumnType> */
    private array $types = [];

    public function add(ColumnType $type): self
    {
        $this->types[$type->id()] = $type;
        return $this;
    }

    public function get(string $id): ?ColumnType
    {
        return $this->types[$id] ?? null;
    }

    /** @return array<string, ColumnType> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return array<string, ColumnType> */
    public function forScreen(ListScreen $screen): array
    {
        return array_filter($this->types, static fn (ColumnType $t) => $t->supports($screen));
    }
}
