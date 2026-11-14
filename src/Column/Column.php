<?php
declare(strict_types=1);

namespace LimeColumns\Column;

/** 列の設定値オブジェクト。型固有の設定は $settings に入る。 */
final class Column
{
    /** @param array<string,mixed> $settings */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        public readonly string $width = '',
        public readonly string $widthUnit = '%',
        public readonly bool $sort = false,
        public readonly bool $search = false,
        public readonly bool $export = false,
        public readonly array $settings = [],
    ) {
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /** @param array<string,mixed> $a */
    public static function fromArray(array $a): self
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) ($a['name'] ?? ''));
        return new self(
            name: $name,
            type: (string) ($a['type'] ?? ''),
            label: (string) ($a['label'] ?? ''),
            width: (string) ($a['width'] ?? ''),
            widthUnit: ($a['width_unit'] ?? '%') === 'px' ? 'px' : '%',
            sort: self::bool($a['sort'] ?? false),
            search: self::bool($a['search'] ?? false),
            export: self::bool($a['export'] ?? false),
            settings: is_array($a['settings'] ?? null) ? $a['settings'] : [],
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label,
            'width' => $this->width,
            'width_unit' => $this->widthUnit,
            'sort' => $this->sort,
            'search' => $this->search,
            'export' => $this->export,
            'settings' => $this->settings,
        ];
    }

    private static function bool(mixed $v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'on' || $v === 'true';
    }
}
