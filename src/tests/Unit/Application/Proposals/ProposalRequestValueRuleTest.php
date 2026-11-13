<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Proposals;

use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Application\Proposals\Services\ProposalRequestValueRule;
use App\Domain\Proposals\Enums\ProposalRequestFieldType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProposalRequestValueRuleTest extends TestCase
{
    /**
     * @return list<ProposalRequestFieldDTO>
     */
    private function fields(): array
    {
        return [
            ProposalRequestFieldDTO::number('capacity', 'Aforo', min: 1, max: 500),
            ProposalRequestFieldDTO::boolean('waitlist', 'Lista de espera')->visibleWhenFilled('capacity'),
            ProposalRequestFieldDTO::select('mode', 'Modo', ['open' => 'Abiertas', 'members' => 'Socios'], required: true),
            ProposalRequestFieldDTO::text('note', 'Nota', maxLength: 10)->visibleWhen('mode', 'members'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, bool|int|string|null>, 1: array<string, string>}
     */
    private function check(array $input): array
    {
        $result = (new ProposalRequestValueRule)->check($this->fields(), $input);

        return [$result->values, $result->errors];
    }

    public function test_it_casts_what_browsers_send_and_keeps_every_field(): void
    {
        [$values, $errors] = $this->check(['capacity' => ' 20 ', 'waitlist' => '1', 'mode' => 'members', 'note' => "  Hola\tmundo ", 'price' => '10']);

        $this->assertSame([], $errors);
        $this->assertSame(['capacity' => 20, 'waitlist' => true, 'mode' => 'members', 'note' => 'Hola mundo'], $values);
    }

    public function test_a_hidden_field_is_null_and_never_refused(): void
    {
        [$values, $errors] = $this->check(['capacity' => '', 'waitlist' => 'nonsense', 'mode' => 'open', 'note' => str_repeat('x', 50)]);

        $this->assertSame([], $errors);
        $this->assertSame(['capacity' => null, 'waitlist' => null, 'mode' => 'open', 'note' => null], $values);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function refusals(): array
    {
        return [
            'below the minimum' => [['capacity' => '0', 'mode' => 'open'], 'capacity', 'min'],
            'above the maximum' => [['capacity' => 501, 'mode' => 'open'], 'capacity', 'max'],
            'not a whole number' => [['capacity' => '2.5', 'mode' => 'open'], 'capacity', 'integer'],
            'not yes or no' => [['capacity' => 5, 'waitlist' => 'maybe', 'mode' => 'open'], 'waitlist', 'boolean'],
            'an unknown option' => [['mode' => 'everyone'], 'mode', 'option'],
            'a required field left empty' => [['mode' => ''], 'mode', 'required'],
            'text too long' => [['mode' => 'members', 'note' => str_repeat('x', 11)], 'note', 'max_length'],
            'text that is not text' => [['mode' => 'members', 'note' => ['x']], 'note', 'text'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('refusals')]
    public function test_it_refuses_what_does_not_fit(array $input, string $field, string $code): void
    {
        [$values, $errors] = $this->check($input);

        $this->assertSame($code, $errors[$field] ?? null);
        $this->assertNull($values[$field]);
    }

    public function test_a_missing_yes_or_no_is_no(): void
    {
        [$values] = $this->check(['capacity' => 3, 'mode' => 'open']);

        $this->assertFalse($values['waitlist']);
    }

    public function test_numeric_option_keys_match_as_text(): void
    {
        $field = ProposalRequestFieldDTO::select('space', 'Espacio', ['12' => 'Sala grande']);

        $this->assertSame(['space' => '12'], (new ProposalRequestValueRule)->check([$field], ['space' => 12])->values);
    }

    /**
     * @return array<string, array{\Closure(): ProposalRequestFieldDTO}>
     */
    public static function badFields(): array
    {
        return [
            'a name that is not snake case' => [fn () => ProposalRequestFieldDTO::boolean('Capacity', 'Aforo')],
            'no label' => [fn () => ProposalRequestFieldDTO::boolean('ok', ' ')],
            'a required yes or no' => [fn () => new ProposalRequestFieldDTO('ok', ProposalRequestFieldType::Boolean, 'Sí', required: true)],
            'min above max' => [fn () => ProposalRequestFieldDTO::number('n', 'N', min: 5, max: 1)],
            'a default outside the limits' => [fn () => ProposalRequestFieldDTO::number('n', 'N', min: 1, max: 5, default: 9)],
            'a select without options' => [fn () => ProposalRequestFieldDTO::select('s', 'S', [])],
            'a default that is not an option' => [fn () => ProposalRequestFieldDTO::select('s', 'S', ['a' => 'A'], default: 'b')],
            'text longer than a line' => [fn () => ProposalRequestFieldDTO::text('t', 'T', maxLength: 256)],
            'depending on itself' => [fn () => ProposalRequestFieldDTO::boolean('b', 'B')->visibleWhenFilled('b')],
        ];
    }

    /**
     * @param  \Closure(): ProposalRequestFieldDTO  $make
     */
    #[DataProvider('badFields')]
    public function test_a_field_that_does_not_fit_cannot_be_built(\Closure $make): void
    {
        $this->expectException(InvalidArgumentException::class);

        $make();
    }
}
