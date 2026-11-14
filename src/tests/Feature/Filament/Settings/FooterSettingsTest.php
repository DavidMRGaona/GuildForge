<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Settings;

use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Navigation\Services\FooterMenusInterface;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Domain\Footer\ValueObjects\FooterBlock;
use App\Domain\Footer\ValueObjects\FooterColumn;
use App\Domain\Footer\ValueObjects\FooterLayout;
use App\Filament\Pages\Settings\FooterSettings;
use App\Filament\Support\PanelNavigationGroup;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
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
