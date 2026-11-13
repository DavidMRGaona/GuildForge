<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Realtime;

use App\Domain\Realtime\RealtimeMessageRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RealtimeMessageRuleTest extends TestCase
{
    /**
     * @param  array<mixed>  $payload
     */
    #[DataProvider('validPayloads')]
    public function test_ids_and_numbers_are_allowed(array $payload): void
    {
        $this->assertTrue(RealtimeMessageRule::isValidPayload($payload));
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function validPayloads(): array
    {
        return [
            'nothing' => [[]],
            'integers' => [['number' => 42, 'count' => 0]],
            'a float' => [['ratio' => 0.5]],
            'a uuid' => [['discussionId' => '0198f1c2-7a3b-7c4d-9e5f-a1b2c3d4e5f6']],
            'an uppercase uuid' => [['discussionId' => '0198F1C2-7A3B-7C4D-9E5F-A1B2C3D4E5F6']],
            'a ulid' => [['postId' => '01HZX3K8Q6Y2M9T4W7B5N1C0RD']],
            'a boolean and null' => [['locked' => true, 'previous' => null]],
            'a list of ids' => [['ids' => ['0198f1c2-7a3b-7c4d-9e5f-a1b2c3d4e5f6', 7]]],
        ];
    }

    /**
     * @param  array<mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_anything_else_is_refused(array $payload): void
    {
        $this->assertFalse(RealtimeMessageRule::isValidPayload($payload));
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'a title' => [['title' => 'La tumba']],
            'a word that looks like an id' => [['slug' => 'la-tumba']],
            'a number as text' => [['number' => '42']],
            'a nested object' => [['post' => ['id' => 42]]],
            'a list with text' => [['ids' => [1, 'hola']]],
            'a nested list' => [['ids' => [[1]]]],
            'a list too long' => [['ids' => range(1, 101)]],
            'an infinite number' => [['ratio' => INF]],
            'not a number' => [['ratio' => NAN]],
            'a list as payload' => [[42]],
            'a key with spaces' => [['the id' => 42]],
            'too many keys' => [array_combine(array_map(static fn (int $i): string => 'k'.$i, range(1, 21)), range(1, 21))],
        ];
    }

    public function test_event_names_are_short_lowercase_words(): void
    {
        $this->assertTrue(RealtimeMessageRule::isValidEventName('site-notification.delivered'));
        $this->assertTrue(RealtimeMessageRule::isValidEventName('post.created'));
        $this->assertFalse(RealtimeMessageRule::isValidEventName(''));
        $this->assertFalse(RealtimeMessageRule::isValidEventName('Post.Created'));
        $this->assertFalse(RealtimeMessageRule::isValidEventName('App\\Events\\PostCreated'));
        $this->assertFalse(RealtimeMessageRule::isValidEventName('pusher:subscription_succeeded'));
        $this->assertFalse(RealtimeMessageRule::isValidEventName('client-typing'));
        $this->assertFalse(RealtimeMessageRule::isValidEventName(str_repeat('a', 65)));
    }
}
