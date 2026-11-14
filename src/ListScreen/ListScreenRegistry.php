<?php
declare(strict_types=1);

namespace LimeColumns\ListScreen;

use LimeColumns\Contracts\ListScreen;

final class ListScreenRegistry
{
    /** @var array<string, ListScreen>|null */
    private ?array $screens = null;

    /** @return array<string, ListScreen> */
    public function all(): array
    {
        if ($this->screens === null) {
            $list = [];
            foreach (get_post_types(['show_ui' => true], 'objects') as $pt) {
                if ($pt->name !== 'attachment') {
                    $list[] = new PostListScreen($pt);
                }
            }
            $list[] = new UserListScreen();
            /** @var ListScreen[] $list */
            $list = apply_filters('lime_columns/list_screens', $list);
            $this->screens = [];
            foreach ($list as $s) {
                $this->screens[$s->key()] = $s;
            }
        }
        return $this->screens;
    }

    public function get(string $key): ?ListScreen
    {
        return $this->all()[$key] ?? null;
    }

    public function forScreen(\WP_Screen $screen): ?ListScreen
    {
        foreach ($this->all() as $s) {
            if ($s->matches($screen)) {
                return $s;
            }
        }
        return null;
    }
}
