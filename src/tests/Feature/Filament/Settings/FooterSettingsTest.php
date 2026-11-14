<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Settings;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Navigation\Services\FooterMenusInterface;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Domain\Footer\ValueObjects\FooterBlock;
use App\Domain\Footer\ValueObjects\FooterColumn;
use App\Domain\Footer\ValueObjects\FooterLayout;
use App\Filament\Pages\Settings\FooterSettings;
use App\Filament\Support\PanelNavigationGroup;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\SiteBlocks\FakeSiteBlock;
use Tests\TestCase;

final class FooterSettingsTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const string MODULE_KEY = 'example-module.subscribe';

    public function test_it_is_a_site_page_for_footer_managers(): void
    {
        $this->assertSame(PanelNavigationGroup::Site->getLabel(), FooterSettings::getNavigationGroup());
        $this->assertSame(25, FooterSettings::getNavigationSort());
        $this->assertSame('Pie de página', FooterSettings::getNavigationLabel());

        $this->actingAs($this->userWith([]));
        $this->assertFalse(FooterSettings::canAccess());

        $this->actingAs($this->userWith(['footer.manage']));
        $this->assertTrue(FooterSettings::canAccess());
        $this->get(FooterSettings::getUrl())->assertOk()->assertSee('Columnas');
    }

    public function test_it_saves_columns_of_blocks(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(FooterSettings::class)
            ->assertOk()
            ->fillForm([
                'columns' => [
                    ['blocks' => [
                        ['type' => 'core.links', 'data' => ['title' => 'La asociación', 'settings' => ['menu' => 'footer_2']]],
                        ['type' => 'core.contact', 'data' => ['title' => '', 'settings' => ['show_email' => true, 'show_phone' => false, 'show_address' => true, 'show_link' => true]]],
                    ]],
                    ['blocks' => [
                        ['type' => 'core.brand', 'data' => ['title' => null, 'settings' => ['show_logo' => true, 'show_name' => false, 'description' => 'Juegos de mesa', 'show_social' => true]]],
                    ]],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Pie de página guardado');

        $layout = app(FooterLayoutServiceInterface::class)->layout();

        $this->assertSame([
            'version' => 1,
            'columns' => [
                ['blocks' => [
                    ['key' => 'core.links', 'title' => 'La asociación', 'settings' => ['menu' => 'footer_2']],
                    ['key' => 'core.contact', 'title' => null, 'settings' => ['show_email' => true, 'show_phone' => false, 'show_address' => true, 'show_link' => true]],
                ]],
                ['blocks' => [
                    ['key' => 'core.brand', 'title' => null, 'settings' => ['show_logo' => true, 'show_name' => false, 'description' => 'Juegos de mesa', 'show_social' => true]],
                ]],
            ],
        ], $layout->toArray());
    }

    public function test_it_loads_the_stored_layout(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([new FooterBlock('core.links', 'Enlaces', ['menu' => 'footer_3'])]),
        ]));

        $component = Livewire::test(FooterSettings::class);
        $columns = array_values($component->get('data.columns'));
        $blocks = array_values($columns[0]['blocks']);

        $this->assertSame('core.links', $blocks[0]['type']);
        $this->assertSame('Enlaces', $blocks[0]['data']['title']);
        $this->assertSame('footer_3', $blocks[0]['data']['settings']['menu']);

        // Saving untouched keeps it as it was
        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame('footer_3', app(FooterLayoutServiceInterface::class)->layout()->columns[0]->blocks[0]->settings['menu']);
    }

    public function test_a_stored_block_that_cannot_be_drawn_now_is_kept_but_cannot_be_added(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $unavailable = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'example-module.subscribe';
            }

            public function settingsSchema(): array
            {
                return [TextInput::make('heading')];
            }

            public function isAvailable(): bool
            {
                return false;
            }
        };
        app(SiteBlockRegistryInterface::class)->register($unavailable::class, 'example-module');
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([new FooterBlock(self::MODULE_KEY, 'Boletín', ['heading' => 'Suscríbete'])]),
        ]));

        $component = Livewire::test(FooterSettings::class)->assertSee('Ahora no se puede mostrar');

        $builder = $this->builderOf($component);
        $this->assertSame(0, $this->blockNamed($builder, self::MODULE_KEY)->getMaxItems());
        $this->assertNull($this->blockNamed($builder, 'core.text')->getMaxItems());

        $component->call('save')->assertHasNoFormErrors();

        $block = app(FooterLayoutServiceInterface::class)->layout()->columns[0]->blocks[0];
        $this->assertSame(self::MODULE_KEY, $block->key);
        $this->assertSame('Boletín', $block->title);
        $this->assertSame(['heading' => 'Suscríbete'], $block->settings);
    }

    public function test_a_stored_block_of_a_disabled_module_survives_saving(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([
                new FooterBlock('disabled-module.subscribe', 'Boletín', ['heading' => 'Suscríbete', 'list' => 3]),
                new FooterBlock('core.text', null, ['content' => '<p>Hola</p>']),
            ]),
        ]));

        Livewire::test(FooterSettings::class)
            ->assertSee('Bloque no disponible (disabled-module.subscribe)')
            ->call('save')
            ->assertHasNoFormErrors();

        $blocks = app(FooterLayoutServiceInterface::class)->layout()->columns[0]->blocks;
        $this->assertSame('disabled-module.subscribe', $blocks[0]->key);
        $this->assertSame('Boletín', $blocks[0]->title);
        $this->assertSame(['heading' => 'Suscríbete', 'list' => 3], $blocks[0]->settings);
        $this->assertSame('core.text', $blocks[1]->key);
    }

    public function test_a_footer_has_at_most_four_columns(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $column = ['blocks' => [['type' => 'core.text', 'data' => ['title' => null, 'settings' => ['content' => '<p>x</p>']]]]];

        Livewire::test(FooterSettings::class)
            ->fillForm(['columns' => array_fill(0, 5, $column)])
            ->call('save')
            ->assertHasFormErrors(['columns']);

        $this->assertTrue(app(FooterLayoutServiceInterface::class)->layout()->isEmpty());
    }

    public function test_removing_every_block_brings_the_fixed_footer_back(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([new FooterBlock('core.text', null, ['content' => '<p>Hola</p>'])]),
        ]));

        Livewire::test(FooterSettings::class)
            ->fillForm(['columns' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(app(FooterLayoutServiceInterface::class)->layout()->isEmpty());
    }

    public function test_a_button_with_a_link_that_is_not_allowed_is_refused(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        foreach (['javascript:alert(1)', '//evil.example', 'ftp://example.com'] as $url) {
            Livewire::test(FooterSettings::class)
                ->fillForm(['columns' => [['blocks' => [
                    ['type' => 'core.button', 'data' => ['title' => null, 'settings' => ['label' => 'Entrar', 'url' => $url, 'style' => 'primary']]],
                ]]]])
                ->call('save')
                ->assertHasFormErrors();
        }

        $this->assertTrue(app(FooterLayoutServiceInterface::class)->layout()->isEmpty());
    }

    public function test_a_button_with_an_https_link_or_a_path_is_saved(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(FooterSettings::class)
            ->fillForm(['columns' => [['blocks' => [
                ['type' => 'core.button', 'data' => ['title' => null, 'settings' => ['label' => 'Únete', 'url' => 'https://example.com/unete', 'style' => 'secondary']]],
                ['type' => 'core.button', 'data' => ['title' => null, 'settings' => ['label' => 'Eventos', 'url' => '/eventos', 'style' => 'primary']]],
                ['type' => 'core.upcoming_events', 'data' => ['title' => 'Próximos', 'settings' => ['limit' => 4, 'show_all_link' => true]]],
            ]]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $blocks = app(FooterLayoutServiceInterface::class)->layout()->columns[0]->blocks;

        $this->assertSame(['core.button', 'core.button', 'core.upcoming_events'], array_map(static fn (FooterBlock $b): string => $b->key, $blocks));
        $this->assertSame('/eventos', $blocks[1]->settings['url']);
        $this->assertSame(4, $blocks[2]->settings['limit']);
    }

    public function test_a_logo_without_alternative_text_is_refused_and_a_bad_link_too(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([new FooterBlock('core.logos', null, ['logos' => [['image' => 'footer/logos/a.png', 'alt' => 'Club', 'url' => '']]])]),
        ]));
        $mount = Livewire::test(FooterSettings::class);
        $logo = static fn (string $alt, string $url): array => ['columns' => [['blocks' => [
            ['type' => 'core.logos', 'data' => ['title' => null, 'settings' => ['logos' => [['image' => ['a' => 'footer/logos/a.png'], 'alt' => $alt, 'url' => $url]]]]],
        ]]]];

        $mount->fillForm($logo('', ''))->call('save')->assertHasFormErrors();
        $mount->fillForm($logo('Club', 'javascript:alert(1)'))->call('save')->assertHasFormErrors();
        $mount->fillForm($logo('Club', 'https://example.com'))->call('save')->assertHasNoFormErrors();

        $this->assertSame('https://example.com', app(FooterLayoutServiceInterface::class)->layout()->columns[0]->blocks[0]->settings['logos'][0]['url']);
    }

    public function test_saving_refreshes_the_media_usages_and_releases_the_logos_that_left(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        // Filament drops from the form any stored file the disk does not have
        Storage::fake('images');
        Storage::disk('images')->put('footer/logos/keep.png', 'x');
        Storage::disk('images')->put('footer/logos/gone.png', 'x');
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([new FooterBlock('core.logos', null, ['logos' => [
                ['image' => 'footer/logos/keep.png', 'alt' => 'Se queda', 'url' => ''],
                ['image' => 'footer/logos/gone.png', 'alt' => 'Se va', 'url' => ''],
            ]])]),
        ]));
        $this->mock(MediaFileReleaserInterface::class, static function (MockInterface $releaser): void {
            $releaser->shouldReceive('release')->once()->with('footer/logos/gone.png');
        });
        $this->mock(MediaIndexerInterface::class, static function (MockInterface $indexer): void {
            $indexer->shouldReceive('refresh')->once()->with('footer', 'layout');
        });

        $component = Livewire::test(FooterSettings::class);
        $columns = $component->get('data.columns');
        $column = array_key_first($columns);
        $block = array_key_first($columns[$column]['blocks']);
        $path = 'data.columns.'.$column.'.blocks.'.$block.'.data.settings.logos';
        $logos = $component->get($path);
        $first = array_key_first($logos);

        $component->set($path, [$first => $logos[$first]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['footer/logos/keep.png'], array_column(app(FooterLayoutServiceInterface::class)->layout()->columns[0]->blocks[0]->settings['logos'], 'image'));
    }

    public function test_a_media_failure_never_fails_the_save(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->mock(MediaIndexerInterface::class, static function (MockInterface $indexer): void {
            $indexer->shouldReceive('refresh')->andThrow(new RuntimeException('index down'));
        });

        Livewire::test(FooterSettings::class)
            ->fillForm(['columns' => [['blocks' => [['type' => 'core.text', 'data' => ['title' => null, 'settings' => ['content' => '<p>Hola</p>']]]]]]])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Pie de página guardado');

        $this->assertFalse(app(FooterLayoutServiceInterface::class)->layout()->isEmpty());
    }

    public function test_it_names_the_footer_menus(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(FooterSettings::class)
            ->fillForm(['menu_names' => ['footer' => 'Principal', 'footer_2' => 'Comunidad', 'footer_3' => '', 'footer_4' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['footer' => 'Principal', 'footer_2' => 'Comunidad'], app(FooterMenusInterface::class)->names());

        Livewire::test(FooterSettings::class)->assertSet('data.menu_names.footer_2', 'Comunidad');
    }

    public function test_saving_is_refused_without_the_permission(): void
    {
        $this->actingAs($this->userWith([]));

        Livewire::test(FooterSettings::class)->assertForbidden();
    }

    public function test_saving_is_refused_once_the_permission_is_taken_away(): void
    {
        $user = $this->userWith(['footer.manage']);
        $this->actingAs($user);
        $page = Livewire::test(FooterSettings::class)
            ->fillForm(['columns' => [['blocks' => [['type' => 'core.text', 'data' => ['title' => 'Forzado', 'settings' => ['content' => '<p>x</p>']]]]]]])
            ->instance();
        $this->assertInstanceOf(FooterSettings::class, $page);

        PermissionModel::query()->where('key', 'footer.manage')->firstOrFail()->roles()->detach();
        app(AuthorizationServiceInterface::class)->invalidateCacheFor($user);

        // The page refuses any later Livewire request; save() checks again on its own
        try {
            $page->save(app(FooterLayoutServiceInterface::class), app(FooterMenusInterface::class));
            $this->fail('Saving without the permission was not refused.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertTrue(app(FooterLayoutServiceInterface::class)->layout()->isEmpty());
    }

    public function test_a_hidden_copy_forged_into_a_block_with_a_form_is_ignored(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(FooterSettings::class)
            ->fillForm(['columns' => [['blocks' => [
                ['type' => 'core.text', 'data' => [
                    'title' => null,
                    'settings' => ['content' => '<p>Del formulario</p>'],
                    'stored_settings' => '{"content":"<p>Forjado</p>","extra":"x"}',
                ]],
                // A key nobody registers and that was never stored
                ['type' => 'forged-module.block', 'data' => ['title' => 'Forjado', 'stored_settings' => '{"html":"<script></script>"}']],
            ]]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $blocks = app(FooterLayoutServiceInterface::class)->layout()->columns[0]->blocks;
        $this->assertCount(1, $blocks);
        $this->assertSame('core.text', $blocks[0]->key);
        $this->assertSame(['content' => '<p>Del formulario</p>'], $blocks[0]->settings);
    }

    public function test_a_core_block_whose_settings_cannot_be_built_keeps_them_when_saving(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $broken = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'core.broken';
            }

            public function settingsSchema(): array
            {
                throw new RuntimeException('Sin ajustes');
            }
        };
        app(SiteBlockRegistryInterface::class)->register($broken::class);
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([new FooterBlock('core.broken', 'Roto', ['limit' => 3, 'label' => 'Hola'])]),
        ]));

        Livewire::test(FooterSettings::class)
            ->assertSee('No se pueden mostrar los ajustes de este bloque')
            ->call('save')
            ->assertHasNoFormErrors();

        $block = app(FooterLayoutServiceInterface::class)->layout()->columns[0]->blocks[0];
        $this->assertSame('core.broken', $block->key);
        $this->assertSame('Roto', $block->title);
        $this->assertSame(['limit' => 3, 'label' => 'Hola'], $block->settings);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): UserModel
    {
        return $this->editorWithPermissions($permissions);
    }

    private function builderOf(mixed $component): Builder
    {
        $form = $component->instance()->form;
        $repeater = $form->getComponent('columns');
        $item = array_first($repeater->getItems());
        $builder = $item->getComponent(static fn ($child): bool => $child instanceof Builder);
        $this->assertInstanceOf(Builder::class, $builder);

        return $builder;
    }

    private function blockNamed(Builder $builder, string $name): Block
    {
        $block = $builder->getBlock($name);
        $this->assertInstanceOf(Block::class, $block);

        return $block;
    }
}
