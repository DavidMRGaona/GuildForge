<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail\ValueObjects;

use App\Domain\Mail\Exceptions\InvalidSmtpPortException;
use App\Domain\Mail\ValueObjects\SmtpPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmtpPortTest extends TestCase
{
    public function test_it_creates_valid_port(): void
    {
        $port = new SmtpPort(587);

        $this->assertEquals(587, $port->value());
    }

    public function test_it_accepts_minimum_valid_port(): void
    {
        $port = new SmtpPort(1);

        $this->assertEquals(1, $port->value());
    }

    public function test_it_accepts_maximum_valid_port(): void
    {
        $port = new SmtpPort(65535);

        $this->assertEquals(65535, $port->value());
    }

    public function test_it_throws_exception_for_port_zero(): void
    {
        $this->expectException(InvalidSmtpPortException::class);

        new SmtpPort(0);
    }

    public function test_it_throws_exception_for_port_above_max(): void
    {
        $this->expectException(InvalidSmtpPortException::class);

        new SmtpPort(65536);
    }

    public function test_it_throws_exception_for_negative_port(): void
    {
        $this->expectException(InvalidSmtpPortException::class);

        new SmtpPort(-1);
    }

    public function test_default_factory_returns_port_587(): void
    {
        $port = SmtpPort::default();

        $this->assertEquals(587, $port->value());
    }

    public function test_ssl_factory_returns_port_465(): void
    {
        $port = SmtpPort::ssl();

        $this->assertEquals(465, $port->value());
    }

    public function test_plain_factory_returns_port_25(): void
    {
        $port = SmtpPort::plain();

        $this->assertEquals(25, $port->value());
    }

    public function test_value_returns_integer(): void
    {
        $port = new SmtpPort(2525);

        $this->assertIsInt($port->value());
        $this->assertEquals(2525, $port->value());
    }

    #[DataProvider('invalidPortsProvider')]
    public function test_it_rejects_out_of_range_ports(int $invalidPort): void
    {
        $this->expectException(InvalidSmtpPortException::class);

        new SmtpPort($invalidPort);
    }

    public static function invalidPortsProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-100],
            'above max' => [65536],
            'very large' => [100000],
        ];
    }
}
