<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Media;

use App\Domain\Media\Enums\MediaDestroyOutcome;
use App\Domain\Media\Exceptions\MediaCatalogUnavailableException;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Cloudinary\CloudinaryMediaCatalog;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CloudinaryMediaCatalogTest extends TestCase
{
    public function test_lists_every_page_of_images_only(): void
    {
        $admin = $this->createMock(AdminApi::class);
        $calls = [];
        $admin->expects($this->exactly(3))->method('assets')->willReturnCallback(
            function (array $options) use (&$calls): ApiResponse {
                $calls[] = $options;

                return match ($options['next_cursor'] ?? null) {
                    null => $this->page(500, 0, 'c1'),
                    'c1' => $this->page(500, 500, 'c2'),
                    default => $this->page(200, 1000, null),
                };
            },
        );

        $assets = $this->catalog(admin: $admin)->all();

        $this->assertCount(1200, $assets);
        $this->assertSame(['image'], array_values(array_unique(array_column($calls, 'resource_type'))));
        $this->assertSame('events/a0', $assets[0]->id->value);
        $this->assertSame('UTC', $assets[0]->uploadedAt->getTimezone()->getName());
        $this->assertSame(10, $assets[0]->bytes);
    }

    public function test_prefix_filter_has_trailing_slash(): void
    {
        $admin = $this->createMock(AdminApi::class);
        $admin->expects($this->once())->method('assets')
            ->with($this->callback(static fn (array $o): bool => $o['prefix'] === 'guildforge/' && $o['type'] === 'upload' && $o['max_results'] === 500))
            ->willReturn($this->page(1, 0, null));

        $this->catalog(admin: $admin)->all();
    }

    public function test_assets_of_another_prefix_are_dropped(): void
    {
        $admin = $this->createStub(AdminApi::class);
        $admin->method('assets')->willReturn(new ApiResponse(['resources' => [
            ['public_id' => 'guildforge-smoke/events/a', 'created_at' => '2026-01-01T00:00:00Z'],
            ['public_id' => 'guildforge/events/b', 'created_at' => '2026-01-01T00:00:00Z'],
        ]], []));

        $assets = $this->catalog(admin: $admin)->all();

        $this->assertCount(1, $assets);
        $this->assertSame('events/b', $assets[0]->id->value);
    }

    public function test_failure_on_a_later_page_throws_and_returns_nothing(): void
    {
        $admin = $this->createStub(AdminApi::class);
        $admin->method('assets')->willReturnCallback(
            fn (array $options): ApiResponse => isset($options['next_cursor'])
                ? throw new RuntimeException('boom')
                : $this->page(5, 0, 'c1'),
        );

        $this->expectException(MediaCatalogUnavailableException::class);

        $this->catalog(admin: $admin)->all();
    }

    public function test_a_repeated_cursor_throws_instead_of_looping(): void
    {
        $admin = $this->createMock(AdminApi::class);
        // c1 → c2 → c1 again: the listing would never end
        $admin->expects($this->exactly(3))->method('assets')->willReturnCallback(
            fn (array $options): ApiResponse => match ($options['next_cursor'] ?? null) {
                null => $this->page(2, 0, 'c1'),
                'c1' => $this->page(2, 2, 'c2'),
                default => $this->page(2, 4, 'c1'),
            },
        );

        $this->expectException(MediaCatalogUnavailableException::class);

        $this->catalog(admin: $admin)->all();
    }

    public function test_destroy_ok_is_deleted(): void
    {
        $this->assertSame(MediaDestroyOutcome::Deleted, $this->destroyWith(new ApiResponse(['result' => 'ok'], [])));
    }

    public function test_destroy_not_found_is_missing(): void
    {
        $this->assertSame(MediaDestroyOutcome::Missing, $this->destroyWith(new ApiResponse(['result' => 'not found'], [])));
    }

    public function test_destroy_other_result_throws(): void
    {
        $this->expectException(MediaCatalogUnavailableException::class);

        $this->destroyWith(new ApiResponse(['result' => 'error'], []));
    }

    public function test_destroy_exception_throws_keeping_the_original(): void
    {
        $upload = $this->createStub(UploadApi::class);
        $upload->method('destroy')->willThrowException($original = new RuntimeException('down'));

        try {
            $this->catalog(upload: $upload)->destroy(new MediaFileId('events/a'), 'image');
            $this->fail('Expected exception');
        } catch (MediaCatalogUnavailableException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    public function test_destroy_sends_resource_type_from_index(): void
    {
        /** @var UploadApi&MockObject $upload */
        $upload = $this->createMock(UploadApi::class);
        $upload->expects($this->once())->method('destroy')
            ->with('guildforge/events/a', ['resource_type' => 'image', 'invalidate' => true])
            ->willReturn(new ApiResponse(['result' => 'ok'], []));

        $this->catalog(upload: $upload)->destroy(new MediaFileId('events/a'), 'image');
    }

    public function test_find_returns_the_asset_with_its_prefixed_public_id(): void
    {
        $admin = $this->createMock(AdminApi::class);
        $admin->expects($this->once())->method('asset')
            ->with('guildforge/events/a', ['resource_type' => 'image'])
            ->willReturn(new ApiResponse([
                'public_id' => 'guildforge/events/a', 'format' => 'jpg', 'bytes' => 99, 'width' => 10, 'height' => 20,
                'created_at' => '2026-03-01T10:00:00+02:00',
            ], []));

        $asset = $this->catalog(admin: $admin)->find(new MediaFileId('events/a'), 'image');

        $this->assertNotNull($asset);
        $this->assertSame('jpg', $asset->format);
        $this->assertSame(20, $asset->height);
        $this->assertSame('2026-03-01 08:00:00', $asset->uploadedAt->format('Y-m-d H:i:s'));
    }

    public function test_find_is_null_when_not_found_and_throws_on_other_failures(): void
    {
        $admin = $this->createStub(AdminApi::class);
        $admin->method('asset')->willThrowException(new NotFound('gone'));
        $this->assertNull($this->catalog(admin: $admin)->find(new MediaFileId('events/a'), 'image'));

        $broken = $this->createStub(AdminApi::class);
        $broken->method('asset')->willThrowException(new RuntimeException('down'));
        $this->expectException(MediaCatalogUnavailableException::class);
        $this->catalog(admin: $broken)->find(new MediaFileId('events/a'), 'image');
    }

    public function test_every_operation_refuses_an_empty_prefix_before_calling_the_api(): void
    {
        $admin = $this->createMock(AdminApi::class);
        $admin->expects($this->never())->method('assets');
        $admin->expects($this->never())->method('asset');
        $upload = $this->createMock(UploadApi::class);
        $upload->expects($this->never())->method('destroy');
        $catalog = $this->catalog($admin, $upload, '');
        $id = new MediaFileId('events/a');

        foreach ([
            $catalog->all(...),
            static fn () => $catalog->find($id, 'image'),
            static fn () => $catalog->destroy($id, 'image'),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected exception');
            } catch (MediaCatalogUnavailableException $e) {
                $this->assertStringContainsString('empty prefix', $e->getMessage());
            }
        }
    }

    private function destroyWith(ApiResponse $response): MediaDestroyOutcome
    {
        $upload = $this->createStub(UploadApi::class);
        $upload->method('destroy')->willReturn($response);

        return $this->catalog(upload: $upload)->destroy(new MediaFileId('events/a'), 'image');
    }

    private function page(int $count, int $offset, ?string $next): ApiResponse
    {
        $resources = [];

        for ($i = 0; $i < $count; $i++) {
            $resources[] = [
                'public_id' => 'guildforge/events/a'.($offset + $i),
                'format' => 'jpg',
                'bytes' => 10,
                'width' => 1,
                'height' => 1,
                'created_at' => '2026-01-01T00:00:00Z',
            ];
        }

        return new ApiResponse($next === null ? ['resources' => $resources] : ['resources' => $resources, 'next_cursor' => $next], []);
    }

    private function catalog(?AdminApi $admin = null, ?UploadApi $upload = null, string $prefix = 'guildforge'): CloudinaryMediaCatalog
    {
        $cloudinary = $this->createStub(Cloudinary::class);
        $cloudinary->method('adminApi')->willReturn($admin ?? $this->createStub(AdminApi::class));
        $cloudinary->method('uploadApi')->willReturn($upload ?? $this->createStub(UploadApi::class));

        return new CloudinaryMediaCatalog($cloudinary, new CloudinaryMediaFileIdResolver($prefix));
    }
}
