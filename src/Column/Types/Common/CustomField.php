<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Common;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Formatter\Format;

/** 任意のメタキー（カスタムフィールド）を表示。投稿・ユーザー共通。 */
final class CustomField extends AbstractColumnType
{
    public function __construct(private readonly string $object = 'post')
    {
    }

    public function id(): string { return $this->object === 'user' ? 'user_custom_field' : 'custom_field'; }
    public function label(): string { return __('Custom field', 'lime-columns'); }
    public function group(): string { return 'custom'; }
    protected function objectTypes(): array { return [$this->object]; }

    public function fields(): array
    {
        return [
            ['name' => 'meta_key', 'label' => __('Meta key', 'lime-columns'), 'type' => 'text'],
            ['name' => 'field_type', 'label' => __('Type', 'lime-columns'), 'type' => 'select', 'default' => 'text',
                'options' => ['text' => 'Text', 'numeric' => 'Number', 'date' => 'Date', 'url' => 'URL', 'image' => 'Image (attachment ID)', 'boolean' => 'True/False', 'json' => 'Array (joined)']],
            ['name' => 'date_mode', 'label' => __('Date format', 'lime-columns'), 'type' => 'select', 'default' => 'wp', 'show_if' => ['field_type' => 'date'],
                'options' => ['wp' => 'WordPress', 'acf' => 'Date only', 'custom' => 'Custom', 'diff' => 'Relative']],
            ['name' => 'date_format', 'label' => 'PHP date format', 'type' => 'text', 'default' => 'Y-m-d', 'show_if' => ['date_mode' => 'custom']],
            ['name' => 'words', 'label' => __('Word limit (0 = none)', 'lime-columns'), 'type' => 'number', 'default' => 0, 'show_if' => ['field_type' => 'text']],
        ];
    }

    public function capabilities(): array { return self::caps(sort: true, search: true); }

    public function sortArgs(Column $column): ?array
    {
        $key = (string) $column->setting('meta_key', '');
        if ($key === '') {
            return null;
        }
        return ['meta_key' => $key, 'orderby' => in_array($column->setting('field_type'), ['numeric', 'boolean'], true) ? 'meta_value_num' : 'meta_value'];
    }

    public function searchMetaKeys(Column $column): array
    {
        $k = (string) $column->setting('meta_key', '');
        return $k === '' ? [] : [$k];
    }

    public function value(Column $column, int $id): string
    {
        $key = (string) $column->setting('meta_key', '');
        if ($key === '') {
            return '';
        }
        $raw = $this->object === 'user' ? get_user_meta($id, $key, true) : get_post_meta($id, $key, true);
        return $this->format($column, $raw);
    }

    public function format(Column $column, mixed $raw): string
    {
        if (is_array($raw)) {
            return implode(', ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : wp_json_encode($v), $raw));
        }
        $raw = (string) $raw;
        return match ((string) $column->setting('field_type', 'text')) {
            'date' => Format::date($raw, (string) $column->setting('date_mode', 'wp'), (string) $column->setting('date_format', '')),
            'boolean' => $raw === '' ? '' : ($raw ? __('Yes', 'lime-columns') : __('No', 'lime-columns')),
            'image' => $raw !== '' ? (string) wp_get_attachment_url((int) $raw) : '',
            'text' => ($w = (int) $column->setting('words', 0)) > 0 ? Format::words($raw, $w) : $raw,
            default => $raw,
        };
    }

    public function render(Column $column, int $id): string
    {
        $type = (string) $column->setting('field_type', 'text');
        $v = $this->value($column, $id);
        if ($v === '') {
            return Format::EMPTY_HTML;
        }
        return match ($type) {
            'url' => sprintf('<a href="%1$s" target="_blank" rel="noopener">%1$s</a>', esc_url($v)),
            'image' => sprintf('<img src="%s" alt="" style="max-width:60px;height:auto">', esc_url($v)),
            default => esc_html($v),
        };
    }
}
