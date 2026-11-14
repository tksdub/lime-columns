<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Common;

use LimeColumns\Column\AbstractColumnType;

/**
 * 他プラグイン・テーマが追加した列をそのまま残すための列タイプ。
 * 列名は元の列キーと同一にし、描画・並び替えは元の実装に任せる。
 */
final class Foreign extends AbstractColumnType
{
    public function id(): string { return 'foreign'; }
    public function label(): string { return __('Column from another plugin/theme', 'lime-columns'); }
    public function group(): string { return 'wp'; }
    protected function objectTypes(): array { return ['post', 'user', 'media', 'comment']; }
    public function capabilities(): array { return self::caps(sort: false, search: false, export: false); }
}
