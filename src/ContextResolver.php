<?php
declare(strict_types=1);

namespace LimeColumns;

use LimeColumns\Column\ColumnRegistry;
use LimeColumns\ListScreen\ListScreenRegistry;
use LimeColumns\Storage\ColumnSetRepository;

/** 画面 or キーから、適用すべき Context を解決する（未設定・無効・権限外は null）。 */
final class ContextResolver
{
    public function __construct(
        private readonly ListScreenRegistry $screens,
        private readonly ColumnSetRepository $repo,
        private readonly ColumnRegistry $types,
    ) {
    }

    public function fromScreen(\WP_Screen $screen): ?Context
    {
        $ls = $this->screens->forScreen($screen);
        return $ls ? $this->fromKey($ls->key()) : null;
    }

    public function fromKey(string $key): ?Context
    {
        $ls = $this->screens->get($key);
        $set = $ls ? $this->repo->find($key) : null;
        if (!$ls || !$set || $set->status !== 'active' || !$this->allowed($set->settings['roles'] ?? [])) {
            return null;
        }
        return new Context($ls, $set, $this->types);
    }

    /** @param mixed $roles 空なら全員に適用 */
    private function allowed(mixed $roles): bool
    {
        if (!is_array($roles) || !$roles) {
            return true;
        }
        $user = wp_get_current_user();
        return (bool) array_intersect($roles, (array) $user->roles);
    }
}
