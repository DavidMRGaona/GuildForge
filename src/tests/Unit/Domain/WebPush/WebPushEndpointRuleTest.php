<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\WebPush;

use App\Domain\WebPush\WebPushEndpointRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebPushEndpointRuleTest extends TestCase
{
    #[DataProvider('pushServices')]
    public function test_it_accepts_the_push_services_of_the_browsers(string $endpoint): void
    {
        $this->assertTrue((new WebPushEndpointRule)->allows($endpoint));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pushServices(): array
    {
        return [
            'chrome' => ['https://fcm.googleapis.com/fcm/send/abc:def'],
            'firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/gAAAA'],
            'safari' => ['https://web.push.apple.com/QGuQyavXutnMH'],
            'edge' => ['https://wns2-par02p.notify.windows.com/w/?token=BQYAAA'],
            'explicit https port' => ['https://fcm.googleapis.com:443/fcm/send/abc'],
        ];
    }

    #[DataProvider('elsewhere')]
    public function test_it_refuses_anything_else_the_site_would_post_to(string $endpoint): void
    {
        $this->assertFalse((new WebPushEndpointRule)->allows($endpoint));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function elsewhere(): array
    {
        return [
            'plain http' => ['http://fcm.googleapis.com/fcm/send/abc'],
            'another host' => ['https://example.test/fcm/send/abc'],
            'a lookalike suffix' => ['https://fcm.googleapis.com.example.test/x'],
            'a lookalike prefix' => ['https://evilpush.apple.com/x'],
            'the bare wildcard domain' => ['https://push.apple.com/x'],
            'user info' => ['https://user:pass@fcm.googleapis.com/fcm/send/abc'],
            'another port' => ['https://fcm.googleapis.com:8443/fcm/send/abc'],
            'an ip address' => ['https://127.0.0.1/x'],
            'a fragment' => ['https://fcm.googleapis.com/fcm/send/abc#x'],
            'whitespace' => ['https://fcm.googleapis.com/fcm/send/a b'],
            'not a url' => ['fcm.googleapis.com/fcm/send/abc'],
            'too long' => ['https://fcm.googleapis.com/'.str_repeat('a', 2048)],
        ];
    }

    public function test_an_installation_can_add_the_push_services_it_needs(): void
    {
        $rule = new WebPushEndpointRule(['push.example.test', '*.push.example.org', 'not a host', '*.']);

        $this->assertTrue($rule->allows('https://push.example.test/abc'));
        $this->assertTrue($rule->allows('https://eu.push.example.org/abc'));
        $this->assertFalse($rule->allows('https://push.example.org/abc'));
        $this->assertFalse($rule->allows('https://other.example.test/abc'));
    }

    public function test_extra_hosts_are_read_from_a_comma_separated_setting(): void
    {
        $this->assertSame(['push.example.test', '*.push.example.org'], WebPushEndpointRule::parseHosts(' Push.Example.test , *.push.example.org,,'));
    }

    public function test_logs_name_the_push_service_only(): void
    {
        $this->assertSame('fcm.googleapis.com', WebPushEndpointRule::serviceOf('https://fcm.googleapis.com/fcm/send/secret-token'));
        $this->assertNull(WebPushEndpointRule::serviceOf('not a url'));
    }
}
