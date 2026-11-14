<?php
declare(strict_types=1);

namespace LimeColumns\Contracts;

use LimeColumns\Column\ColumnRegistry;

/** 他プラグインとの連携。available() が true のときだけ列タイプを登録する。 */
interface Integration
{
    public function id(): string;

    public function available(): bool;

    public function register(ColumnRegistry $types): void;
}
