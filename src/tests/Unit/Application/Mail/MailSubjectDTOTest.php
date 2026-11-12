<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Mail;

use App\Application\Mail\DTOs\MailSubjectDTO;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailSubjectDTOTest extends TestCase
{
    public function test_it_keeps_the_type_the_id_and_the_names(): void
    {
        $subject = new MailSubjectDTO(
            type: 'game-tables.campaign',
            id: '0193b2c4-7f1e-7a00-9c3d-5e6f7a8b9c0d',
            label: 'La tumba de los horrores',
            typeLabel: 'game-tables::mail.subjects.campaign',
        );

        $this->assertSame('game-tables.campaign', $subject->type);
        $this->assertSame('0193b2c4-7f1e-7a00-9c3d-5e6f7a8b9c0d', $subject->id);
        $this->assertSame('La tumba de los horrores', $subject->label);
        $this->assertSame('game-tables::mail.subjects.campaign', $subject->typeLabel);
    }

    public function test_the_type_label_is_optional(): void
    {
        $this->assertNull((new MailSubjectDTO('tournaments.tournament', 'abc_123', 'Liga de otoño'))->typeLabel);
    }

    public function test_the_name_is_trimmed_and_kept_on_one_line(): void
    {
        $subject = new MailSubjectDTO('tournaments.tournament', '42', "  Liga\n de\t\totoño \r\n");

        $this->assertSame('Liga de otoño', $subject->label);
    }

    public function test_a_long_name_is_cut_to_the_maximum_length(): void
    {
        $subject = new MailSubjectDTO('tournaments.tournament', '42', str_repeat('ñ', 200));

        $this->assertSame(MailSubjectDTO::LABEL_MAX_LENGTH, mb_strlen($subject->label));
    }

    /**
     * @return array<string, array{string, string, string, string|null}>
     */
    public static function invalidSubjects(): array
    {
        return [
            'type without module' => ['campaign', '1', 'Nombre', null],
            'type with capitals' => ['Game-Tables.campaign', '1', 'Nombre', null],
            'type with a slash' => ['game-tables/campaign', '1', 'Nombre', null],
            'empty id' => ['game-tables.campaign', '', 'Nombre', null],
            'id with a slash' => ['game-tables.campaign', 'a/b', 'Nombre', null],
            'id too long' => ['game-tables.campaign', str_repeat('a', 65), 'Nombre', null],
            'blank name' => ['game-tables.campaign', '1', " \n\t ", null],
            'invalid UTF-8 name' => ['game-tables.campaign', '1', "\xC3\x28", null],
            'type label with spaces' => ['game-tables.campaign', '1', 'Nombre', 'una campaña'],
            'type label too long' => ['game-tables.campaign', '1', 'Nombre', str_repeat('a', 151)],
            'type with a trailing newline' => ["game-tables.campaign\n", '1', 'Nombre', null],
            'id with a trailing newline' => ['game-tables.campaign', "abc\n", 'Nombre', null],
            'type label with a trailing newline' => ['game-tables.campaign', '1', 'Nombre', "a.b\n"],
        ];
    }

    #[DataProvider('invalidSubjects')]
    public function test_it_rejects_what_cannot_be_stored_or_put_in_a_link(string $type, string $id, string $label, ?string $typeLabel): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MailSubjectDTO($type, $id, $label, $typeLabel);
    }
}
