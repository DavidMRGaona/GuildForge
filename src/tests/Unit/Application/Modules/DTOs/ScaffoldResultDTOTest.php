<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Modules\DTOs;

use App\Application\Modules\DTOs\ScaffoldResultDTO;
use PHPUnit\Framework\TestCase;

final class ScaffoldResultDTOTest extends TestCase
{
    public function test_it_creates_successful_result_via_success_factory(): void
    {
        $message = 'Module scaffold completed successfully';
        $files = [
            'ServiceProvider.php' => ScaffoldResultDTO::STATUS_CREATED,
            'Controller.php' => ScaffoldResultDTO::STATUS_CREATED,
        ];
        $warnings = ['Config file already exists'];

        $dto = ScaffoldResultDTO::success($message, $files, $warnings);

        $this->assertTrue($dto->success);
        $this->assertEquals($message, $dto->message);
        $this->assertEquals($files, $dto->files);
        $this->assertEquals($warnings, $dto->warnings);
        $this->assertEmpty($dto->errors);
    }

    public function test_it_creates_failed_result_via_failure_factory(): void
    {
        $message = 'Module scaffold failed';
        $errors = ['Invalid module name', 'Directory not writable'];

        $dto = ScaffoldResultDTO::failure($message, $errors);

        $this->assertFalse($dto->success);
        $this->assertEquals($message, $dto->message);
        $this->assertEquals($errors, $dto->errors);
        $this->assertEmpty($dto->files);
        $this->assertEmpty($dto->warnings);
    }

    public function test_is_success_returns_true_for_successful_result(): void
    {
        $dto = ScaffoldResultDTO::success('Success');

        $this->assertTrue($dto->isSuccess());
        $this->assertFalse($dto->isFailure());
    }

    public function test_is_failure_returns_true_for_failed_result(): void
    {
        $dto = ScaffoldResultDTO::failure('Failed');

        $this->assertTrue($dto->isFailure());
        $this->assertFalse($dto->isSuccess());
    }

    public function test_has_warnings_returns_true_when_warnings_present(): void
    {
        $dto = ScaffoldResultDTO::success('Success', [], ['Warning message']);

        $this->assertTrue($dto->hasWarnings());
    }

    public function test_has_warnings_returns_false_when_no_warnings(): void
    {
        $dto = ScaffoldResultDTO::success('Success');

        $this->assertFalse($dto->hasWarnings());
    }

    public function test_count_files_by_status_returns_correct_count_for_created(): void
    {
        $files = [
            'File1.php' => ScaffoldResultDTO::STATUS_CREATED,
            'File2.php' => ScaffoldResultDTO::STATUS_CREATED,
            'File3.php' => ScaffoldResultDTO::STATUS_SKIPPED,
        ];

        $dto = ScaffoldResultDTO::success('Success', $files);

        $this->assertEquals(2, $dto->countFilesByStatus(ScaffoldResultDTO::STATUS_CREATED));
    }

    public function test_count_files_by_status_returns_correct_count_for_skipped(): void
    {
        $files = [
            'File1.php' => ScaffoldResultDTO::STATUS_CREATED,
            'File2.php' => ScaffoldResultDTO::STATUS_SKIPPED,
            'File3.php' => ScaffoldResultDTO::STATUS_SKIPPED,
        ];

        $dto = ScaffoldResultDTO::success('Success', $files);

        $this->assertEquals(2, $dto->countFilesByStatus(ScaffoldResultDTO::STATUS_SKIPPED));
    }

    public function test_count_files_by_status_returns_correct_count_for_overwritten(): void
    {
        $files = [
            'File1.php' => ScaffoldResultDTO::STATUS_OVERWRITTEN,
            'File2.php' => ScaffoldResultDTO::STATUS_CREATED,
        ];

        $dto = ScaffoldResultDTO::success('Success', $files);

        $this->assertEquals(1, $dto->countFilesByStatus(ScaffoldResultDTO::STATUS_OVERWRITTEN));
    }

    public function test_count_files_by_status_returns_zero_when_no_matches(): void
    {
        $files = [
            'File1.php' => ScaffoldResultDTO::STATUS_CREATED,
            'File2.php' => ScaffoldResultDTO::STATUS_CREATED,
        ];

        $dto = ScaffoldResultDTO::success('Success', $files);

        $this->assertEquals(0, $dto->countFilesByStatus(ScaffoldResultDTO::STATUS_FAILED));
    }

    public function test_to_array_returns_complete_representation(): void
    {
        $files = [
            'Provider.php' => ScaffoldResultDTO::STATUS_CREATED,
        ];
        $warnings = ['Minor issue'];
        $errors = ['Critical error'];

        $dto = new ScaffoldResultDTO(
            success: false,
            message: 'Partial failure',
            files: $files,
            errors: $errors,
            warnings: $warnings,
        );

        $array = $dto->toArray();

        $expected = [
            'success' => false,
            'message' => 'Partial failure',
            'files' => $files,
            'errors' => $errors,
            'warnings' => $warnings,
        ];

        $this->assertEquals($expected, $array);
    }

    public function test_to_array_includes_empty_arrays_for_optional_fields(): void
    {
        $dto = ScaffoldResultDTO::success('Success');

        $array = $dto->toArray();

        $this->assertArrayHasKey('files', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('warnings', $array);
        $this->assertEmpty($array['files']);
        $this->assertEmpty($array['errors']);
        $this->assertEmpty($array['warnings']);
    }

    public function test_it_handles_all_status_constants(): void
    {
        $files = [
            'created.php' => ScaffoldResultDTO::STATUS_CREATED,
            'skipped.php' => ScaffoldResultDTO::STATUS_SKIPPED,
            'overwritten.php' => ScaffoldResultDTO::STATUS_OVERWRITTEN,
            'failed.php' => ScaffoldResultDTO::STATUS_FAILED,
        ];

        $dto = ScaffoldResultDTO::success('Mixed status', $files);

        $this->assertEquals(1, $dto->countFilesByStatus(ScaffoldResultDTO::STATUS_CREATED));
        $this->assertEquals(1, $dto->countFilesByStatus(ScaffoldResultDTO::STATUS_SKIPPED));
        $this->assertEquals(1, $dto->countFilesByStatus(ScaffoldResultDTO::STATUS_OVERWRITTEN));
        $this->assertEquals(1, $dto->countFilesByStatus(ScaffoldResultDTO::STATUS_FAILED));
    }
}
