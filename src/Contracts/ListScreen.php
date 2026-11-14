<?php
declare(strict_types=1);

namespace LimeColumns\Contracts;

/** 一覧画面（投稿タイプ別の投稿一覧、ユーザー一覧など）の抽象。 */
interface ListScreen
{
    /** 一意キー。例: `post:treatise`, `user` */
    public function key(): string;

    public function label(): string;

    /** 設定UIでのグループ名。 */
    public function group(): string;

    /** `post` | `user` | `media` | `comment` など。列タイプの対応判定に使う。 */
    public function objectType(): string;

    /** この一覧のエクスポート等に必要な権限（capability名）。 */
    public function capability(): string;

    public function matches(\WP_Screen $screen): bool;

    public function columnsFilter(): string;

    public function sortableFilter(): string;

    /**
     * セル描画のフックを登録する。
     * @param callable(string, int): ?string $render 列名とオブジェクトIDを受け取りHTMLを返す。自分の列でなければ null
     */
    public function attachCellRenderer(callable $render): void;

    /**
     * 一覧のメインクエリにフックを登録する。
     * @param callable(object): void $callback
     */
    public function attachQuery(callable $callback): void;

    /** 指定クエリがこの一覧のクエリか（メインクエリ、またはエクスポート用の印付きクエリ）。 */
    public function isListQuery(object $query): bool;

    public function requestedOrderby(object $query): string;

    /** @param array<string,mixed> $args 列タイプの sortArgs() の戻り値 */
    public function applyOrder(object $query, array $args): void;

    public function searchTerm(object $query): string;

    /** @return array<string,string> 列名 => ラベル。WP標準の列。 */
    public function nativeColumns(): array;

    /**
     * リクエスト（一覧のクエリ文字列）から、エクスポート対象のIDを全件返す。
     * @param array<string,mixed> $request
     * @return int[]
     */
    public function exportIds(array $request): array;

    /** 一覧上部に出力する追加HTMLの出力フックを登録する。 */
    public function attachToolbar(callable $print): void;
}
