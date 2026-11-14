<?php
declare(strict_types=1);

namespace LimeColumns;

/** 軽量DIコンテナ。ファクトリは初回取得時に1度だけ実行される。 */
final class Container
{
    /** @var array<string, callable(Container): mixed> */
    private array $factories = [];
    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->instances)) {
            if (!isset($this->factories[$id])) {
                throw new \RuntimeException("LimeColumns: service not found: {$id}");
            }
            $this->instances[$id] = ($this->factories[$id])($this);
        }
        return $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
