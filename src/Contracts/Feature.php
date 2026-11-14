<?php
declare(strict_types=1);

namespace LimeColumns\Contracts;

use LimeColumns\Container;

/** 機能単位の拡張点。`lime_columns/features` で追加・除外できる。 */
interface Feature
{
    public function id(): string;

    /** 一覧画面に作用するか（false の機能は設定UI/REST側でのみ使う）。 */
    public function appliesToListScreens(): bool;

    public function boot(Container $c): void;
}
