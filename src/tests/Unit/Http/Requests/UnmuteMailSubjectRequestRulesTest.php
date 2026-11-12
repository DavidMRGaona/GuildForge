<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Auth\UnmuteMailSubjectRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class UnmuteMailSubjectRequestRulesTest extends TestCase
{
    public function test_a_valid_type_and_id_pass(): void
    {
        $this->assertFalse($this->validator('game-tables.campaign', 'abc-1')->fails());
    }

    public function test_a_type_with_a_trailing_newline_is_rejected(): void
    {
        $this->assertTrue($this->validator("game-tables.campaign\n", 'abc')->errors()->has('type'));
    }

    public function test_an_id_with_a_trailing_newline_is_rejected(): void
    {
        $this->assertTrue($this->validator('game-tables.campaign', "abc\n")->errors()->has('id'));
    }

    private function validator(string $type, string $id): \Illuminate\Validation\Validator
    {
        $validator = Validator::make(
            ['type' => $type, 'id' => $id],
            (new UnmuteMailSubjectRequest)->rules(),
        );
        $validator->fails();

        return $validator;
    }
}
