<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Media;

use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Services\MediaSourceRegistry;
use ArrayObject;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use stdClass;
use Tests\Support\Media\FakeMediaSource;
use Tests\Support\Media\UnresolvableMediaSource;
use Tests\TestCase;

final class MediaSourceRegistryTest extends TestCase
{
    private MediaSourceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new MediaSourceRegistry($this->app);
    }

    /**
     * Binds an alias to a configured fake and returns it as the class to register.
     *
     * @param  list<string>  $folders
     * @param  list<string>  $excluded
     * @return class-string
     */
    private function fake(string $type, array $folders, bool $hidden = false, array $excluded = []): string
    {
        $alias = 'test.media.'.$type;
        $this->app->bind($alias, fn (): FakeMediaSource => new FakeMediaSource($type, $folders, $hidden, $excluded));

        /** @var class-string $alias */
        return $alias;
    }

    /**
     * @return class-string
     */
    private function alias(string $alias): string
    {
        /** @var class-string $alias */
        return $alias;
    }

    #[Test]
    public function test_claiming_source_is_the_longest_matching_folder(): void
    {
        $this->registry->registerMany([
            $this->fake('link', ['link-page/']),
            $this->fake('qr', ['link-page/qr/']),
        ]);

        $this->assertSame('qr', $this->registry->claimingSource(new MediaFileId('link-page/qr/x'))?->type());
        $this->assertSame('link', $this->registry->claimingSource(new MediaFileId('link-page/x'))?->type());
    }

    #[Test]
    public function test_folder_match_respects_the_slash(): void
    {
        $this->registry->register($this->fake('events', ['events/']));

        $this->assertNull($this->registry->claimingSource(new MediaFileId('events-archive/x')));
        $this->assertSame('events', $this->registry->claimingSource(new MediaFileId('events/2026/x'))?->type());
    }

    #[Test]
    public function test_excluded_subfolder_is_not_claimed_by_the_source(): void
    {
        $this->registry->register($this->fake('articles', ['articles/'], excluded: ['content/']));

        $this->assertNull($this->registry->claimingSource(new MediaFileId('articles/content/2026/11/x')));
        $this->assertSame('articles', $this->registry->claimingSource(new MediaFileId('articles/2026/11/x'))?->type());
    }

    #[Test]
    public function test_a_second_source_claims_the_excluded_subfolder(): void
    {
        $this->registry->registerMany([
            $this->fake('articles', ['articles/'], excluded: ['content/']),
            $this->fake('articles.text', ['articles/content/']),
        ]);

        $this->assertSame('articles.text', $this->registry->claimingSource(new MediaFileId('articles/content/2026/11/x'))?->type());
        $this->assertSame('articles', $this->registry->claimingSource(new MediaFileId('articles/2026/11/x'))?->type());
    }

    #[Test]
    public function test_duplicate_type_keeps_the_first_and_logs_a_warning(): void
    {
        $log = Log::spy();
        $first = $this->fake('dup', ['a/']);
        $this->app->bind('test.media.dup2', fn (): FakeMediaSource => new FakeMediaSource('dup', ['b/']));
        $this->registry->registerMany([$first, $this->alias('test.media.dup2')]);

        $sources = $this->registry->sources();

        $this->assertCount(1, $sources);
        $this->assertSame(['a/'], $sources[0]->folders());
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function test_unresolvable_class_is_skipped_and_logged(): void
    {
        $log = Log::spy();
        $this->registry->registerMany([UnresolvableMediaSource::class, $this->fake('ok', ['ok/'])]);

        $this->assertCount(1, $this->registry->sources());
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function test_unresolved_lists_the_classes_that_could_not_be_loaded(): void
    {
        $this->app->bind('test.media.not-a-source', static fn (): stdClass => new stdClass);
        $this->app->bind('test.media.broken-type', static fn (): FakeMediaSource => new class extends FakeMediaSource
        {
            public function type(): string
            {
                throw new RuntimeException('no type');
            }
        });
        $this->registry->registerMany([
            UnresolvableMediaSource::class,
            $this->alias('test.media.not-a-source'),
            $this->alias('test.media.broken-type'),
            $this->fake('ok', ['ok/']),
        ]);
        // A source skipped because its type is taken cannot be asked either, so it is listed too
        $this->app->bind('test.media.ok-again', static fn (): FakeMediaSource => new FakeMediaSource('ok', ['again/']));
        $this->registry->register($this->alias('test.media.ok-again'));

        $this->assertSame([
            UnresolvableMediaSource::class,
            'test.media.not-a-source',
            'test.media.broken-type',
            'test.media.ok-again',
        ], $this->registry->unresolved());
        $this->assertCount(1, $this->registry->sources());

        $this->registry->clear();

        $this->assertSame([], $this->registry->unresolved());
    }

    #[Test]
    public function test_unresolved_is_empty_when_every_source_loads(): void
    {
        $this->registry->register($this->fake('ok', ['ok/']));

        $this->assertSame([], $this->registry->unresolved());
    }

    #[Test]
    public function test_hidden_folders_lists_only_hidden_sources(): void
    {
        $this->registry->registerMany([
            $this->fake('events', ['events/']),
            $this->fake('avatars', ['avatars/'], hidden: true),
        ]);

        $this->assertSame(['avatars/'], $this->registry->hiddenFolders());
    }

    #[Test]
    public function test_unregister_module_drops_its_sources(): void
    {
        $this->registry->register($this->fake('core', ['core/']));
        $this->registry->register($this->fake('mod', ['mod/']), 'demo');

        $this->assertSame('demo', $this->registry->moduleOf('mod'));
        $this->assertNull($this->registry->moduleOf('core'));

        $this->registry->unregisterModule('demo');

        $this->assertCount(1, $this->registry->sources());
        $this->assertNull($this->registry->source('mod'));
        $this->assertSame('core', $this->registry->source('core')?->type());
    }

    #[Test]
    public function test_resolving_twice_builds_each_source_once_and_logs_a_broken_class_once(): void
    {
        $log = Log::spy();
        $builds = new ArrayObject;
        $this->app->bind('test.media.counted', function () use ($builds): FakeMediaSource {
            $builds->append(true);

            return new FakeMediaSource('counted', ['counted/']);
        });
        $this->registry->registerMany([UnresolvableMediaSource::class, $this->alias('test.media.counted')]);

        $this->registry->sources();
        $this->registry->claimingSource(new MediaFileId('counted/a'));
        $this->registry->source('counted');
        $this->registry->hiddenFolders();

        $this->assertCount(1, $builds);
        $log->shouldHaveReceived('warning')->once();

        // Registering again resolves again, but the broken class is not reported twice
        $this->registry->register($this->fake('other', ['other/']));

        $this->assertCount(2, $this->registry->sources());
        $this->assertCount(2, $builds);
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function test_every_change_to_the_registrations_clears_the_resolved_sources(): void
    {
        $this->registry->register($this->fake('core', ['core/']));
        $this->assertCount(1, $this->registry->sources());

        $this->registry->registerMany([$this->fake('mod', ['mod/'])], 'demo');
        $this->assertCount(2, $this->registry->sources());

        $this->registry->unregisterModule('demo');
        $this->assertCount(1, $this->registry->sources());

        $this->registry->clear();
        $this->assertSame([], $this->registry->sources());
    }
}
