<?php

declare(strict_types=1);

namespace Tests\Unit\View\Modules;

use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\CompatibilityResult;
use App\View\Modules\CompatibilityIssueFormatter;
use Tests\TestCase;

final class CompatibilityIssueFormatterTest extends TestCase
{
    private CompatibilityIssueFormatter $formatter;

    private CompatibilityIssue $core;

    private CompatibilityIssue $extension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new CompatibilityIssueFormatter();
        $this->core = new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED);
        $this->extension = new CompatibilityIssue(RequirementType::Extension, 'intl', null, CompatibilityIssue::MISSING_EXTENSION);
    }

    public function test_it_translates_each_reason(): void
    {
        $this->assertSame('requiere core ^3.0, instalado 2.6.0', $this->formatter->format($this->core));
        $this->assertSame('requiere la extensión PHP intl', $this->formatter->format($this->extension));
        $this->assertSame('restricción de PHP no válida: «>=8.x»', $this->formatter->format(
            new CompatibilityIssue(RequirementType::Php, '>=8.x', '8.4.26', CompatibilityIssue::INVALID_CONSTRAINT),
        ));
        $this->assertSame('no se encuentra module.json', $this->formatter->format(
            new CompatibilityIssue(RequirementType::Manifest, '', null, CompatibilityIssue::MANIFEST_MISSING),
        ));
    }

    public function test_it_accepts_persisted_arrays(): void
    {
        $this->assertSame('requiere core ^3.0, instalado 2.6.0', $this->formatter->format($this->core->toArray()));
    }

    public function test_the_short_form_omits_the_installed_version(): void
    {
        $this->assertSame('requiere core ^3.0', $this->formatter->formatShort($this->core));
        $this->assertSame('requiere la extensión PHP intl', $this->formatter->formatShort($this->extension));
    }

    public function test_summary_joins_with_semicolons(): void
    {
        $this->assertSame('requiere core ^3.0, instalado 2.6.0; requiere la extensión PHP intl', $this->formatter->summary([$this->core, $this->extension]));
        $this->assertSame('requiere core ^3.0; requiere la extensión PHP intl', $this->formatter->summary([$this->core, $this->extension], short: true));
    }

    public function test_list_label(): void
    {
        $this->assertSame('sí', $this->formatter->listLabel(CompatibilityResult::compatible()));
        $this->assertSame('no: requiere core ^3.0', $this->formatter->listLabel(new CompatibilityResult([$this->core])));
        $this->assertSame('no: requiere core ^3.0 (+1)', $this->formatter->listLabel(new CompatibilityResult([$this->core, $this->extension])));
    }

    public function test_messages_for_enabling_and_installing(): void
    {
        $this->assertSame('No se puede habilitar Anuncios: requiere core ^3.0, instalado 2.6.0', $this->formatter->cannotEnable('Anuncios', [$this->core]));
        $this->assertSame(
            'El paquete announcements 9.0.0 no es compatible: requiere core ^3.0, instalado 2.6.0',
            $this->formatter->incompatiblePackage('announcements', '9.0.0', [$this->core]),
        );
    }
}
