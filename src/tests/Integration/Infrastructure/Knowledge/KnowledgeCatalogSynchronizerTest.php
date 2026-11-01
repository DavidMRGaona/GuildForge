<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideAliasModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Knowledge\KnowledgeFixtures;
use Tests\TestCase;

final class KnowledgeCatalogSynchronizerTest extends TestCase
{
    use KnowledgeFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKnowledgeSandbox();
    }

    protected function tearDown(): void
    {
        $this->tearDownKnowledgeSandbox();
        parent::tearDown();
    }

    private function guide(string $id, string $locale = 'es'): KnowledgeGuideModel
    {
        return KnowledgeGuideModel::query()->where('guide_id', $id)->where('locale', $locale)->firstOrFail();
    }

    public function test_first_sync_sets_a_baseline_without_new_badges(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->writeCoreGuide('b.md', 'account.two');

        $report = $this->syncKnowledge();

        $this->assertSame(2, $report->inserted);
        $this->assertSame([], $report->errors);
        $this->assertNull($this->guide('account.one')->first_seen_at);
        $this->assertNull($this->guide('account.two')->first_seen_at);
        $this->assertSame('core', $this->guide('account.one')->source);
        $this->assertSame('markdown', $this->guide('account.one')->body_format);
        $this->assertSame('es', $this->guide('account.one')->locale);
    }

    public function test_guides_added_later_get_first_seen_at(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->syncKnowledge();

        $this->writeCoreGuide('b.md', 'account.two');
        $report = $this->syncKnowledge();

        $this->assertSame(1, $report->inserted);
        $this->assertNull($this->guide('account.one')->first_seen_at);
        $this->assertNotNull($this->guide('account.two')->first_seen_at);
    }

    public function test_changed_content_sets_content_changed_at(): void
    {
        $this->writeCoreGuide('a.md', 'account.one', body: 'Antes.');
        $this->syncKnowledge();
        $this->assertNull($this->guide('account.one')->content_changed_at);

        $this->writeCoreGuide('a.md', 'account.one', body: 'Después.');
        $report = $this->syncKnowledge();

        $this->assertSame(1, $report->updated);
        $guide = $this->guide('account.one');
        $this->assertNotNull($guide->content_changed_at);
        $this->assertStringContainsString('Después.', $guide->body);
        $this->assertStringContainsString('Después.', $guide->body_text);
    }

    public function test_plain_text_follows_placeholder_settings_without_touching_content_timestamps(): void
    {
        $settings = $this->app->make(SettingsServiceInterface::class);
        $settings->set('guild_name', 'Primer nombre');
        $this->writeCoreGuide('a.md', 'account.one', body: 'Antes.');
        $this->syncKnowledge();
        $this->writeCoreGuide('a.md', 'account.one', body: 'Bienvenido a {{site_name}}.');
        $this->syncKnowledge();
        $changedAt = $this->guide('account.one')->content_changed_at;
        $this->assertNotNull($changedAt);
        $this->assertStringContainsString('Primer nombre', $this->guide('account.one')->body_text);

        $settings->set('guild_name', 'Segundo nombre');
        $report = $this->syncKnowledge();

        $guide = $this->guide('account.one');
        $this->assertStringContainsString('Segundo nombre', $guide->body_text);
        $this->assertStringNotContainsString('Primer nombre', $guide->body_text);
        $this->assertEquals($changedAt, $guide->content_changed_at);
        $this->assertSame(0, $report->updated);
    }

    public function test_plain_text_is_built_in_the_guide_locale_without_callout_titles(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set('association_member_role', 'rol-inexistente');
        $this->writeCoreGuide('a.md', 'account.one', body: "> [!WARNING]\n> Careful.\n\nYou are {{association_role}}.", locale: 'en');
        $this->writeCoreGuide('b.md', 'account.one', body: "> [!WARNING]\n> Cuidado.\n\nEres {{association_role}}.", locale: 'es');

        app()->setLocale('es');
        $this->syncKnowledge();

        $en = $this->guide('account.one', 'en')->body_text;
        $this->assertStringContainsString('You are member', $en);
        $this->assertStringNotContainsString('Ojo', $en);
        $this->assertStringNotContainsString('Warning', $en);
        $this->assertStringContainsString('Eres socio', $this->guide('account.one', 'es')->body_text);
        $this->assertStringNotContainsString('Ojo', $this->guide('account.one', 'es')->body_text);
        $this->assertSame('es', app()->getLocale());
    }

    public function test_unchanged_content_keeps_timestamps(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->syncKnowledge();
        $this->writeCoreGuide('b.md', 'account.two');
        $this->syncKnowledge();
        $before = $this->guide('account.two');

        $report = $this->syncKnowledge();

        $this->assertSame(0, $report->inserted + $report->updated + $report->deactivated);
        $after = $this->guide('account.two');
        $this->assertEquals($before->first_seen_at, $after->first_seen_at);
        $this->assertNull($after->content_changed_at);
    }

    public function test_missing_guides_are_deactivated_not_deleted(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->writeCoreGuide('b.md', 'account.two');
        $this->syncKnowledge();

        $this->deleteCoreFile('b.md');
        $report = $this->syncKnowledge();

        $this->assertSame(1, $report->deactivated);
        $this->assertFalse($this->guide('account.two')->active);
        $this->assertTrue($this->guide('account.one')->active);
        $this->assertSame(0, $this->syncKnowledge()->deactivated);
    }

    public function test_reappearing_guides_are_reactivated_keeping_first_seen_at(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->syncKnowledge();
        $this->writeCoreGuide('b.md', 'account.two');
        $this->syncKnowledge();
        $firstSeen = $this->guide('account.two')->first_seen_at;
        $this->assertNotNull($firstSeen);

        $this->deleteCoreFile('b.md');
        $this->syncKnowledge();
        $this->writeCoreGuide('b.md', 'account.two');
        $this->syncKnowledge();

        $guide = $this->guide('account.two');
        $this->assertTrue($guide->active);
        $this->assertEquals($firstSeen, $guide->first_seen_at);
    }

    public function test_duplicate_ids_keep_the_core_guide_and_report_the_rest(): void
    {
        $this->writeModuleGuide('zeta', 'x.md', 'account.one', 'De zeta');
        $this->writeModuleGuide('alpha', 'x.md', 'account.one', 'De alfa');
        $this->writeCoreGuide('a.md', 'account.one', 'Del core');

        $report = $this->syncKnowledge();

        $this->assertSame(1, $report->inserted);
        $this->assertCount(2, $report->errors);
        $this->assertSame('Del core', $this->guide('account.one')->title);
        $this->assertSame('core', $this->guide('account.one')->source);
    }

    public function test_duplicate_ids_between_modules_keep_the_first_alphabetically(): void
    {
        $this->writeModuleGuide('zeta', 'x.md', 'account.one', 'De zeta');
        $this->writeModuleGuide('alpha', 'x.md', 'account.one', 'De alfa');

        $report = $this->syncKnowledge();

        $this->assertCount(1, $report->errors);
        $this->assertSame('De alfa', $this->guide('account.one')->title);
        $this->assertSame('module', $this->guide('account.one')->source);
        $this->assertSame('alpha', $this->guide('account.one')->module);
    }

    public function test_invalid_files_are_reported_and_skipped(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->writeFile($this->corePath().'/es/broken.md', "no front matter here\n");

        $report = $this->syncKnowledge();

        $this->assertSame(1, $report->inserted);
        $this->assertCount(1, $report->errors);
        $this->assertStringContainsString('broken.md', $report->errors[0]);
    }

    public function test_previous_ids_become_aliases(): void
    {
        $this->writeCoreGuide('a.md', 'account.one', extra: "previous_ids:\n  - account.old\n");

        $this->syncKnowledge();
        $this->syncKnowledge();

        $alias = KnowledgeGuideAliasModel::query()->where('alias_id', 'account.old')->sole();
        $this->assertSame('account.one', $alias->guide_id);
    }

    public function test_core_categories_are_synced(): void
    {
        $this->syncKnowledge();

        $category = KnowledgeCategoryModel::query()->where('category_id', 'getting-started')->sole();
        $this->assertNull($category->module);
        $this->assertSame('site', $category->audience);
        $this->assertSame('knowledge.categories.getting-started', $category->label_key);
    }

    public function test_module_categories_are_synced_and_removed_when_module_disabled(): void
    {
        $this->writeModuleCategories('alpha', <<<'PHP'
<?php

return [
    ['id' => 'alpha-tables', 'audience' => 'site', 'order' => 5, 'icon' => 'heroicon-o-table-cells', 'label_key' => 'alpha::knowledge.tables'],
];
PHP);

        $this->syncKnowledge();
        $category = KnowledgeCategoryModel::query()->where('category_id', 'alpha-tables')->sole();
        $this->assertSame('alpha', $category->module);
        $this->assertSame('alpha::knowledge.tables', $category->label_key);

        $this->disableKnowledgeModule('alpha');
        $this->syncKnowledge();

        $this->assertSame(0, KnowledgeCategoryModel::query()->where('category_id', 'alpha-tables')->count());
        $this->assertSame(1, KnowledgeCategoryModel::query()->where('category_id', 'getting-started')->count());
    }

    public function test_guides_of_a_disabled_module_are_deactivated(): void
    {
        $this->writeModuleGuide('alpha', 'x.md', 'alpha.guide');
        $this->syncKnowledge();

        $this->disableKnowledgeModule('alpha');
        $report = $this->syncKnowledge();

        $this->assertSame(1, $report->deactivated);
        $this->assertFalse($this->guide('alpha.guide')->active);
    }

    public function test_a_malformed_module_category_file_is_reported(): void
    {
        $this->writeModuleCategories('alpha', "<?php\n\nreturn [['id' => 'broken']];\n");

        $report = $this->syncKnowledge();

        $this->assertCount(1, $report->errors);
        $this->assertSame(0, KnowledgeCategoryModel::query()->where('category_id', 'broken')->count());
    }

    public function test_a_guide_whose_file_becomes_invalid_stays_active_with_its_old_content(): void
    {
        $this->writeCoreGuide('a.md', 'account.one', 'Original');
        $this->writeCoreGuide('b.md', 'account.two');
        $this->syncKnowledge();

        $this->writeFile($this->corePath().'/es/a.md', "broken\n");
        $this->deleteCoreFile('b.md');
        $report = $this->syncKnowledge();

        $this->assertCount(1, $report->errors);
        $this->assertSame(0, $report->deactivated);
        $this->assertTrue($this->guide('account.one')->active);
        $this->assertSame('Original', $this->guide('account.one')->title);
        $this->assertTrue($this->guide('account.two')->active);
    }

    public function test_a_broken_file_does_not_protect_guides_of_other_sources(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->writeModuleGuide('alpha', 'x.md', 'alpha.guide');
        $this->syncKnowledge();

        $this->writeFile($this->corePath().'/es/a.md', "broken\n");
        unlink($this->knowledgeSandbox.'/modules/alpha/resources/knowledge/es/x.md');
        $report = $this->syncKnowledge();

        $this->assertSame(1, $report->deactivated);
        $this->assertTrue($this->guide('account.one')->active);
        $this->assertFalse($this->guide('alpha.guide')->active);
    }
}
