<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\User;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Formatter\Format;

/** ユーザーテーブルの単純なプロパティ（ID・登録日・URL・表示名）。 */
final class UserField extends AbstractColumnType
{
    private const DEFS = [
        'user_id' => ['User ID', 'ID', 'ID'],
        'registered' => ['Registered', 'user_registered', 'registered'],
        'user_url' => ['Website', 'user_url', 'url'],
        'display_name' => ['Display name', 'display_name', 'display_name'],
    ];

    public function __construct(private readonly string $kind)
    {
    }

    public function id(): string { return $this->kind; }
    public function label(): string { return __(self::DEFS[$this->kind][0], 'lime-columns'); }
    protected function objectTypes(): array { return ['user']; }
    public function capabilities(): array { return self::caps(sort: true); }

    public function fields(): array
    {
        return $this->kind === 'registered' ? [
            ['name' => 'date_mode', 'label' => __('Date format', 'lime-columns'), 'type' => 'select', 'default' => 'wp',
                'options' => ['wp' => 'WordPress', 'custom' => 'Custom', 'diff' => 'Relative']],
            ['name' => 'date_format', 'label' => 'PHP date format', 'type' => 'text', 'default' => 'Y-m-d', 'show_if' => ['date_mode' => 'custom']],
        ] : [];
    }

    public function sortArgs(Column $column): ?array
    {
        return ['orderby' => self::DEFS[$this->kind][2]];
    }

    public function value(Column $column, int $id): string
    {
        $u = get_userdata($id);
        if (!$u) {
            return '';
        }
        $raw = (string) $u->{self::DEFS[$this->kind][1]};
        return $this->kind === 'registered'
            ? Format::date($raw, (string) $column->setting('date_mode', 'wp'), (string) $column->setting('date_format', ''))
            : $raw;
    }
}
