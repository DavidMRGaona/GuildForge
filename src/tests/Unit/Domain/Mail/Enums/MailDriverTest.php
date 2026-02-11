<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail\Enums;

use App\Domain\Mail\Enums\MailDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailDriverTest extends TestCase
{
    public function test_it_has_exactly_six_cases(): void
    {
        $this->assertCount(6, MailDriver::cases());
    }

    public function test_it_has_smtp_case(): void
    {
        $this->assertContains(MailDriver::Smtp, MailDriver::cases());
    }

    public function test_it_has_mail_case(): void
    {
        $this->assertContains(MailDriver::Mail, MailDriver::cases());
    }

    public function test_it_has_ses_case(): void
    {
        $this->assertContains(MailDriver::Ses, MailDriver::cases());
    }

    public function test_it_has_resend_case(): void
    {
        $this->assertContains(MailDriver::Resend, MailDriver::cases());
    }

    public function test_it_has_log_case(): void
    {
        $this->assertContains(MailDriver::Log, MailDriver::cases());
    }

    public function test_it_has_array_case(): void
    {
        $this->assertContains(MailDriver::Array_, MailDriver::cases());
    }

    #[DataProvider('valuesProvider')]
    public function test_cases_have_correct_string_values(MailDriver $driver, string $expectedValue): void
    {
        $this->assertEquals($expectedValue, $driver->value);
    }

    public static function valuesProvider(): array
    {
        return [
            'smtp' => [MailDriver::Smtp, 'smtp'],
            'mail' => [MailDriver::Mail, 'mail'],
            'ses' => [MailDriver::Ses, 'ses'],
            'resend' => [MailDriver::Resend, 'resend'],
            'log' => [MailDriver::Log, 'log'],
            'array' => [MailDriver::Array_, 'array'],
        ];
    }

    #[DataProvider('labelsProvider')]
    public function test_label_returns_correct_human_readable_labels(MailDriver $driver, string $expectedLabel): void
    {
        $this->assertEquals($expectedLabel, $driver->label());
    }

    public static function labelsProvider(): array
    {
        return [
            'smtp' => [MailDriver::Smtp, 'SMTP'],
            'mail' => [MailDriver::Mail, 'PHP Mail'],
            'ses' => [MailDriver::Ses, 'Amazon SES'],
            'resend' => [MailDriver::Resend, 'Resend'],
            'log' => [MailDriver::Log, 'Log (desarrollo)'],
            'array' => [MailDriver::Array_, 'Array (pruebas)'],
        ];
    }

    public function test_requires_smtp_config_returns_true_for_smtp(): void
    {
        $this->assertTrue(MailDriver::Smtp->requiresSmtpConfig());
    }

    #[DataProvider('driversNotRequiringSmtpConfigProvider')]
    public function test_requires_smtp_config_returns_false_for_non_smtp_drivers(MailDriver $driver): void
    {
        $this->assertFalse($driver->requiresSmtpConfig());
    }

    public static function driversNotRequiringSmtpConfigProvider(): array
    {
        return [
            'mail' => [MailDriver::Mail],
            'ses' => [MailDriver::Ses],
            'resend' => [MailDriver::Resend],
            'log' => [MailDriver::Log],
            'array' => [MailDriver::Array_],
        ];
    }

    public function test_requires_ses_config_returns_true_for_ses(): void
    {
        $this->assertTrue(MailDriver::Ses->requiresSesConfig());
    }

    #[DataProvider('driversNotRequiringSesConfigProvider')]
    public function test_requires_ses_config_returns_false_for_non_ses_drivers(MailDriver $driver): void
    {
        $this->assertFalse($driver->requiresSesConfig());
    }

    public static function driversNotRequiringSesConfigProvider(): array
    {
        return [
            'smtp' => [MailDriver::Smtp],
            'mail' => [MailDriver::Mail],
            'resend' => [MailDriver::Resend],
            'log' => [MailDriver::Log],
            'array' => [MailDriver::Array_],
        ];
    }

    public function test_requires_resend_config_returns_true_for_resend(): void
    {
        $this->assertTrue(MailDriver::Resend->requiresResendConfig());
    }

    #[DataProvider('driversNotRequiringResendConfigProvider')]
    public function test_requires_resend_config_returns_false_for_non_resend_drivers(MailDriver $driver): void
    {
        $this->assertFalse($driver->requiresResendConfig());
    }

    public static function driversNotRequiringResendConfigProvider(): array
    {
        return [
            'smtp' => [MailDriver::Smtp],
            'mail' => [MailDriver::Mail],
            'ses' => [MailDriver::Ses],
            'log' => [MailDriver::Log],
            'array' => [MailDriver::Array_],
        ];
    }

    public function test_it_can_create_from_string_value(): void
    {
        $driver = MailDriver::from('smtp');

        $this->assertEquals(MailDriver::Smtp, $driver);
    }
}
