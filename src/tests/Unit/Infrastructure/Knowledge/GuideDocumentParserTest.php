<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Knowledge;

use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\InvalidGuideDocumentException;
use App\Infrastructure\Knowledge\Parsing\GuideDocumentParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GuideDocumentParserTest extends TestCase
{
    private GuideDocumentParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new GuideDocumentParser;
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/knowledge/'.$name);
    }

    public function test_it_parses_a_complete_guide(): void
    {
        $contents = $this->fixture('complete.md');
        $dto = $this->parser->parse('x.md', $contents);

        $this->assertSame('core.account.change-password', $dto->id->value());
        $this->assertSame(['core.account.password'], array_map(strval(...), $dto->previousIds));
        $this->assertSame('Cambiar la contraseña', $dto->title);
        $this->assertSame('account', $dto->categoryId);
        $this->assertSame(GuideAudience::Site, $dto->audience);
        $this->assertSame(10, $dto->order);
        $this->assertSame(['contraseña', 'clave'], $dto->keywords);
        $this->assertSame(['core.profile-complete'], array_map(strval(...), $dto->related));
        $this->assertTrue($dto->featured);
        $this->assertTrue($dto->conditions->associationMember);
        $this->assertSame(['users.update'], $dto->conditions->permissionsAll);
        $this->assertStringStartsWith('1. Abre', trim($dto->body));
        $this->assertSame(sha1($contents), $dto->contentHash);
    }

    public function test_defaults_apply_when_optional_fields_are_missing(): void
    {
        $dto = $this->parser->parse('m.md', $this->fixture('minimal.md'));

        $this->assertSame(100, $dto->order);
        $this->assertFalse($dto->featured);
        $this->assertSame([], $dto->keywords);
        $this->assertSame([], $dto->related);
        $this->assertSame([], $dto->previousIds);
        $this->assertSame([], $dto->conditions->toArray());
        $this->assertSame(GuideAudience::Panel, $dto->audience);
    }

    #[DataProvider('brokenDocuments')]
    public function test_it_rejects_broken_documents(string $contents, string $expectedField): void
    {
        try {
            $this->parser->parse('broken.md', $contents);
            $this->fail('Expected InvalidGuideDocumentException');
        } catch (InvalidGuideDocumentException $e) {
            $this->assertStringContainsString($expectedField, $e->getMessage());
            $this->assertStringContainsString('broken.md', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function brokenDocuments(): array
    {
        $base = "id: core.a\ntitle: T\nsummary: S\ncategory: c\naudience: site\n";

        return [
            'no front matter' => ["Solo texto\n", "'front matter'"],
            'invalid yaml' => ["---\nid: [unclosed\n---\nx\n", "'front matter'"],
            'missing title' => ["---\nid: core.a\nsummary: S\ncategory: c\naudience: site\n---\nx", "'title'"],
            'missing id' => ["---\ntitle: T\nsummary: S\ncategory: c\naudience: site\n---\nx", "'id'"],
            'bad audience' => ["---\n".str_replace('audience: site', 'audience: admin', $base)."---\nx", "'audience'"],
            'bad id' => ["---\n".str_replace('core.a', 'Bad Id', $base)."---\nx", "'id'"],
            'bad related id' => ["---\n".$base."related: [Bad Id]\n---\nx", "'related'"],
            'bad previous id' => ["---\n".$base."previous_ids: [nodots]\n---\nx", "'previous_ids'"],
            'order not integer' => ["---\n".$base."order: soon\n---\nx", "'order'"],
            'keywords not list' => ["---\n".$base."keywords: abc\n---\nx", "'keywords'"],
            'featured not bool' => ["---\n".$base."featured: maybe\n---\nx", "'featured'"],
            'unknown condition' => ["---\n".$base."conditions:\n  roles: [admin]\n---\nx", "'conditions'"],
        ];
    }
}
