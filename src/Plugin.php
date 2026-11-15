<?php
declare(strict_types=1);

namespace LimeColumns;

use LimeColumns\Admin\AjaxController;
use LimeColumns\Admin\RestController;
use LimeColumns\Admin\SettingsPage;
use LimeColumns\Admin\Tools\AcpImporter;
use LimeColumns\Column\ColumnRegistry;
use LimeColumns\Column\Types;
use LimeColumns\Contracts\Feature;
use LimeColumns\Contracts\Integration;
use LimeColumns\Features;
use LimeColumns\Integrations\Acf\AcfIntegration;
use LimeColumns\ListScreen\ListScreenRegistry;
use LimeColumns\Storage\ColumnSetRepository;
use LimeColumns\Storage\Schema;

/** 起動点。サービス登録と、機能・連携の組み立てだけを行う。 */
final class Plugin
{
    private static ?self $instance = null;
    private Container $c;
    private bool $booted = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function container(): Container
    {
        return $this->c;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
        $this->c = new Container();
        $this->registerServices();

        load_plugin_textdomain('lime-columns', false, dirname(plugin_basename(LIME_COLUMNS_FILE)) . '/languages');

        if (is_admin()) {
            Schema::maybeUpgrade();
        }

        /** @var Feature[] $features */
        $features = apply_filters('lime_columns/features', [
            new Features\Display\DisplayFeature(),
            new Features\Sorting\SortingFeature(),
            new Features\DefaultSort\DefaultSortFeature(),
            new Features\Search\SearchFeature(),
            new Features\Export\ExportFeature(),
        ]);
        foreach ($features as $f) {
            $f->boot($this->c);
        }

        (new SettingsPage())->register();
        (new AjaxController($this->c))->register();
        (new RestController($this->c))->register();

        if (is_admin()) {
            add_action('current_screen', [$this, 'onCurrentScreen'], 5);
            add_action('admin_notices', [$this, 'coexistNotice']);
        }

        do_action('lime_columns/loaded', $this->c);
    }

    /** ACP が有効な間は、二重適用を避けるため一覧画面への適用を止める（設定UI・移行ツールは使える）。 */
    public static function acpActive(): bool
    {
        return defined('ACP_FILE') || defined('AC_FILE');
    }

    public static function appliesToLists(): bool
    {
        return !self::acpActive() || (bool) apply_filters('lime_columns/allow_coexist_with_acp', false);
    }

    public function onCurrentScreen(\WP_Screen $screen): void
    {
        if (!self::appliesToLists()) {
            return;
        }
        $ctx = $this->c->get('resolver')->fromScreen($screen);
        if ($ctx) {
            do_action('lime_columns/context_ready', $ctx);
        }
    }

    public function coexistNotice(): void
    {
        $screen = get_current_screen();
        if (self::appliesToLists() || !$screen || $screen->id !== 'settings_page_lime-columns') {
            return;
        }
        echo '<div class="notice notice-warning"><p>'
            . esc_html__('Admin Columns Pro is active, so Lime Columns is not applied to list screens. Deactivate it to switch over (settings and the importer remain available).', 'lime-columns')
            . '</p></div>';
    }

    private function registerServices(): void
    {
        $this->c->set('repository', static fn () => new ColumnSetRepository());
        $this->c->set('screens', static fn () => new ListScreenRegistry());
        $this->c->set('types', function () {
            $r = new ColumnRegistry();
            foreach ($this->builtinTypes() as $t) {
                $r->add($t);
            }
            /** @var Integration[] $integrations */
            $integrations = apply_filters('lime_columns/integrations', [new AcfIntegration()]);
            foreach ($integrations as $i) {
                if ($i->available()) {
                    $i->register($r);
                }
            }
            do_action('lime_columns/register_column_types', $r);
            return $r;
        });
        $this->c->set('resolver', fn (Container $c) => new ContextResolver($c->get('screens'), $c->get('repository'), $c->get('types')));
        $this->c->set('importer', fn (Container $c) => new AcpImporter($c->get('repository'), $c->get('types'), $c->get('screens')));
    }

    /** @return list<Contracts\ColumnType> */
    private function builtinTypes(): array
    {
        return [
            new Types\Post\Title(), new Types\Post\Author(), new Types\Post\Date(), new Types\Post\Comments(),
            new Types\Post\AuthorDisplay(), new Types\Post\Thumbnail(), new Types\Post\Taxonomy(), new Types\Post\LatestComment(),
            new Types\Post\PostField('post_id'), new Types\Post\PostField('slug'), new Types\Post\PostField('status'),
            new Types\Post\PostField('modified'), new Types\Post\PostField('excerpt'), new Types\Post\PostField('content'),
            new Types\User\NativeUserColumn('username'), new Types\User\NativeUserColumn('user_name'),
            new Types\User\NativeUserColumn('email'), new Types\User\NativeUserColumn('role'), new Types\User\NativeUserColumn('posts'),
            new Types\User\UserField('user_id'), new Types\User\UserField('registered'),
            new Types\User\UserField('user_url'), new Types\User\UserField('display_name'),
            new Types\Common\CustomField('post'), new Types\Common\CustomField('user'),
            new Types\Common\Foreign(),
        ];
    }
}
