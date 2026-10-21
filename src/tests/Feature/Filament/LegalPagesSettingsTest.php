<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Legal\DTOs\LegalMailNoticeDTO;
use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class LegalPagesSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string KEY = 'fake-module.invitation';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_the_tab_is_hidden_without_notices(): void
    {
        Livewire::test(LegalPagesSettings::class)
            ->assertOk()
            ->assertDontSee('Avisos en correos');
    }

    public function test_the_tab_saves_each_notice(): void
    {
        $this->registerNotice();
        $field = LegalPagesSettings::noticeFieldName(self::KEY);

        Livewire::test(LegalPagesSettings::class)
            ->assertSee('Avisos en correos')
            ->assertSee('Aviso de prueba')
            ->assertSee('Huecos disponibles: :site, :days. Déjalo vacío para usar el texto por defecto.')
            ->assertFormSet([$field => ''])
            ->fillForm([$field => "Texto propio\n\nSegundo párrafo"])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsServiceInterface::class);
        $this->assertSame("Texto propio\n\nSegundo párrafo", $settings->get('legal_mail_notice:'.self::KEY));

        // Saving again with the text kept must not blank it, and reloading shows it.
        Livewire::test(LegalPagesSettings::class)
            ->assertFormSet([$field => "Texto propio\n\nSegundo párrafo"]);
    }

    public function test_the_other_tabs_do_not_blank_the_notices_when_the_registry_is_empty(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('legal_mail_notice:'.self::KEY, 'Guardado');

        Livewire::test(LegalPagesSettings::class)->call('save');

        $this->assertSame('Guardado', $settings->get('legal_mail_notice:'.self::KEY));
    }

    public function test_the_preview_renders_the_form_text(): void
    {
        $this->registerNotice();
        $field = LegalPagesSettings::noticeFieldName(self::KEY);

        $page = Livewire::test(LegalPagesSettings::class)
            ->fillForm([$field => 'Texto sin guardar'])
            ->mountAction($this->previewAction());

        $action = $page->instance()->getMountedAction();
        $this->assertNotNull($action);
        $this->assertSame('Vista previa: Aviso de prueba', (string) $action->getModalHeading());

        $content = (string) $action->getModalContent();
        $this->assertStringContainsString('sandbox', $content);
        $this->assertStringContainsString('srcdoc="&lt;p class=&quot;preview&quot;&gt;Texto sin guardar&lt;/p&gt;"', $content);

        $this->assertNull(app(SettingsServiceInterface::class)->get('legal_mail_notice:'.self::KEY));
    }

    public function test_the_preview_uses_the_default_when_the_field_is_empty(): void
    {
        $this->registerNotice();

        $page = Livewire::test(LegalPagesSettings::class)
            ->mountAction($this->previewAction());

        $content = (string) $page->instance()->getMountedAction()?->getModalContent();
        $this->assertStringContainsString('Texto por defecto', $content);
    }

    private function previewAction(): TestAction
    {
        return TestAction::make(LegalPagesSettings::noticePreviewActionName(self::KEY))
            ->schemaComponent(LegalPagesSettings::noticeSectionKey(self::KEY));
    }

    private function registerNotice(): void
    {
        app(LegalMailNoticeRegistryInterface::class)->register(new LegalMailNoticeDTO(
            key: self::KEY,
            module: 'fake-module',
            label: 'Aviso de prueba',
            help: 'Ayuda',
            defaultContent: 'Texto por defecto',
            placeholders: [':site', ':days'],
            preview: static fn (string $content): string => '<p class="preview">'.$content.'</p>',
        ));
    }
}
