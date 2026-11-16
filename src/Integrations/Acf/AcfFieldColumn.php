<?php
declare(strict_types=1);

namespace LimeColumns\Integrations\Acf;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Formatter\Format;

/** ACFフィールドの値を、フィールド型に応じて整形して表示する。 */
final class AcfFieldColumn extends AbstractColumnType
{
    public function __construct(private readonly string $object = 'post')
    {
    }

    public function id(): string { return $this->object === 'user' ? 'user_acf_field' : 'acf_field'; }
    public function label(): string { return __('ACF field', 'lime-columns'); }
    public function group(): string { return 'custom'; }
    protected function objectTypes(): array { return $this->object === 'post' ? ['post', 'media'] : [$this->object]; }
    public function capabilities(): array { return self::caps(sort: true, search: true); }

    public function fields(): array
    {
        $options = [];
        foreach (acf_get_field_groups() as $group) {
            foreach ((array) acf_get_fields($group['key']) as $f) {
                $options[$f['key']] = sprintf('%s › %s (%s)', $group['title'], $f['label'], $f['name']);
            }
        }
        return [
            ['name' => 'field_key', 'label' => __('ACF field', 'lime-columns'), 'type' => 'select', 'options' => $options],
            ['name' => 'sub_field', 'label' => __('Repeater sub field key', 'lime-columns'), 'type' => 'text', 'help' => 'field_xxxx'],
            ['name' => 'date_mode', 'label' => __('Date format', 'lime-columns'), 'type' => 'select', 'default' => 'acf',
                'options' => ['acf' => 'WordPress (date only)', 'wp' => 'WordPress (date + time)', 'custom' => 'Custom', 'diff' => 'Relative']],
            ['name' => 'date_format', 'label' => __('PHP date format', 'lime-columns'), 'type' => 'text', 'default' => 'Y-m-d', 'show_if' => ['date_mode' => 'custom']],
        ];
    }

    /**
     * 設定UIの「列タイプ」一覧に1件ずつ出すACFフィールド。フィールドグループの表示条件で画面に絞り込む。
     * @return list<array{key:string,label:string,group:string}>
     */
    public function pickable(string $postType = ''): array
    {
        // 添付ファイル（メディア）の ACF 位置ルールは post_type ではなく attachment（all / MIME の大分類）で判定される。
        if ($this->object === 'user') {
            $argSets = [['user_form' => 'edit']];
        } elseif ($postType === 'attachment') {
            $argSets = array_map(static fn (string $t) => ['attachment' => $t], ['all', 'image', 'video', 'audio', 'application', 'text']);
        } else {
            $argSets = [['post_type' => $postType]];
        }
        $groups = [];
        foreach ($argSets as $args) {
            foreach (acf_get_field_groups($args) as $group) {
                $groups[(string) $group['key']] = $group;
            }
        }
        $out = [];
        foreach ($groups as $group) {
            foreach ((array) acf_get_fields($group['key']) as $f) {
                if (in_array($f['type'], ['tab', 'message', 'accordion'], true)) {
                    continue;
                }
                $out[] = ['key' => (string) $f['key'], 'label' => (string) $f['label'], 'group' => (string) $group['title']];
            }
        }
        return $out;
    }

    private function ref(int $id): string|int
    {
        return $this->object === 'user' ? 'user_' . $id : $id;
    }

    /** @return array<string,mixed>|null */
    private function field(Column $column): ?array
    {
        $key = (string) $column->setting('field_key', '');
        $f = $key !== '' ? acf_get_field($key) : null;
        return is_array($f) ? $f : null;
    }

    public function sortArgs(Column $column): ?array
    {
        $f = $this->field($column);
        if (!$f || in_array($f['type'], ['repeater', 'group', 'flexible_content', 'gallery', 'relationship'], true)) {
            return null;
        }
        return ['meta_key' => $f['name'], 'orderby' => in_array($f['type'], ['number', 'range', 'true_false'], true) ? 'meta_value_num' : 'meta_value'];
    }

    public function searchMetaKeys(Column $column): array
    {
        $f = $this->field($column);
        return $f && !in_array($f['type'], ['repeater', 'group', 'flexible_content'], true) ? [(string) $f['name']] : [];
    }

    public function value(Column $column, int $id): string
    {
        $f = $this->field($column);
        if (!$f) {
            return '';
        }
        $raw = get_field($f['key'], $this->ref($id), false);
        if ($f['type'] === 'repeater' && ($sub = (string) $column->setting('sub_field', '')) !== '') {
            return implode(', ', array_filter(array_map(
                fn ($row) => $this->scalar($column, $this->subDef($f, $sub), $row[$this->subDef($f, $sub)['name'] ?? ''] ?? null),
                is_array($raw) ? $raw : []
            ), static fn ($v) => $v !== ''));
        }
        return $this->scalar($column, $f, $raw);
    }

    /** @return array<string,mixed> */
    private function subDef(array $field, string $key): array
    {
        foreach ((array) ($field['sub_fields'] ?? []) as $s) {
            if (($s['key'] ?? '') === $key) {
                return $s;
            }
        }
        return ['type' => 'text', 'name' => ''];
    }

    /** @param array<string,mixed> $f */
    private function scalar(Column $column, array $f, mixed $raw): string
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return '';
        }
        switch ($f['type'] ?? 'text') {
            case 'date_picker':
            case 'date_time_picker':
                return Format::date((string) $raw, (string) $column->setting('date_mode', 'acf'), (string) $column->setting('date_format', ''));
            case 'true_false':
                return $raw ? __('Yes', 'lime-columns') : __('No', 'lime-columns');
            case 'select':
            case 'radio':
            case 'button_group':
            case 'checkbox':
                $choices = (array) ($f['choices'] ?? []);
                return implode(', ', array_map(static fn ($v) => (string) ($choices[$v] ?? $v), (array) $raw));
            case 'file':
            case 'image':
                $aid = is_array($raw) ? (int) ($raw['ID'] ?? 0) : (int) $raw;
                return $aid ? (string) basename((string) get_attached_file($aid)) : '';
            case 'post_object':
            case 'relationship':
            case 'page_link':
                return implode(', ', array_map(static fn ($p) => get_the_title(is_object($p) ? $p->ID : (int) $p), (array) $raw));
            case 'user':
                return implode(', ', array_map(static fn ($u) => (string) (get_userdata(is_array($u) ? (int) $u['ID'] : (int) $u)->display_name ?? ''), (array) $raw));
            default:
                return is_array($raw) ? implode(', ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $raw)) : (string) $raw;
        }
    }
}
