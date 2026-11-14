<?php
declare(strict_types=1);

namespace LimeColumns\Storage;

use LimeColumns\Column\Column;

/** 1つの一覧画面に対する列セットと一覧設定。 */
final class ColumnSet
{
    /**
     * @param list<Column> $columns
     * @param array<string,mixed> $settings
     */
    public function __construct(
        public readonly string $listKey,
        public readonly array $columns = [],
        public readonly array $settings = [],
        public readonly string $title = '',
        public readonly string $status = 'active',
    ) {
    }

    public function column(string $name): ?Column
    {
        foreach ($this->columns as $c) {
            if ($c->name === $name) {
                return $c;
            }
        }
        return null;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'list_key' => $this->listKey,
            'title' => $this->title,
            'status' => $this->status,
            'settings' => (object) $this->settings,
            'columns' => array_map(static fn (Column $c) => $c->toArray(), $this->columns),
        ];
    }
}
