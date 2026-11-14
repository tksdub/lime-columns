<?php
declare(strict_types=1);

namespace LimeColumns\Contracts;

use LimeColumns\Column\Column;

/** 列タイプ（状態を持たない定義）。列の実体は Column（設定値）。 */
interface ColumnType
{
    public function id(): string;

    public function label(): string;

    /** 設定UIのグループ名。 */
    public function group(): string;

    public function supports(ListScreen $screen): bool;

    /** WP標準の列キーを使う型ならそのキー（WPの描画・行アクションをそのまま使う）。 */
    public function nativeKey(): ?string;

    /**
     * 型固有の設定項目のスキーマ。
     * @return list<array{name:string,label:string,type:string,default?:mixed,options?:array<string,string>,help?:string,show_if?:array<string,string>}>
     */
    public function fields(): array;

    /** @return array{sort:bool,search:bool,export:bool} */
    public function capabilities(): array;

    /** セルのHTML（エスケープ済み）。 */
    public function render(Column $column, int $id): string;

    /** CSV等で使うプレーンテキスト値。 */
    public function value(Column $column, int $id): string;

    /** 並び替え用クエリ引数。並び替え不可なら null。 */
    public function sortArgs(Column $column): ?array;

    /** 検索対象にするメタキー。 */
    public function searchMetaKeys(Column $column): array;
}
