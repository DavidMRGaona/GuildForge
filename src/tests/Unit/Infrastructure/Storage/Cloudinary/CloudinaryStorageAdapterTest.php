<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Storage\Cloudinary;

use App\Application\Services\ImageOptimizationServiceInterface;
use App\Infrastructure\Storage\Cloudinary\CloudinaryPathMapper;
use App\Infrastructure\Storage\Cloudinary\CloudinaryStorageAdapter;
use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Api\Exception\RateLimited;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Config;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToRetrieveMetadata;
use LogicException;
use Mockery;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

final class CloudinaryStorageAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        Log::clearResolvedInstance();
        Mockery::close();

        parent::tearDown();
    }

    public function test_write_uploads_a_data_uri_with_the_prefixed_public_id_and_its_folder(): void
    {
        $upload = $this->createMock(UploadApi::class);
        $upload->expects($this->once())->method('upload')->with(
            'data:image/jpeg;base64,'.base64_encode('bytes'),
            ['public_id' => 'guildforge/events/2026/01/abc', 'resource_type' => 'image', 'asset_folder' => 'guildforge/events/2026/01'],
        );

        $this->adapter(upload: $upload)->write('events/2026/01/abc.jpg', 'bytes', new Config());
    }

    public function test_write_stream_uploads_the_optimized_image(): void
    {
        $optimizer = $this->createStub(ImageOptimizationServiceInterface::class);
        $optimizer->method('optimize')->willReturn('small');
        $upload = $this->createMock(UploadApi::class);
        $upload->expects($this->once())->method('upload')->with(
            'data:image/png;base64,'.base64_encode('small'),
            ['public_id' => 'guildforge/hero/slide', 'resource_type' => 'image', 'asset_folder' => 'guildforge/hero'],
        );
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, 'large');
        rewind($stream);

        $this->adapter(upload: $upload, optimizer: $optimizer)->writeStream('hero/slide.png', $stream, new Config());
    }

    public function test_upload_errors_reach_the_caller(): void
    {
        $upload = $this->createStub(UploadApi::class);
        $upload->method('upload')->willThrowException(new RateLimited('slow down'));

        $this->expectException(RateLimited::class);

        $this->adapter(upload: $upload)->write('events/a.jpg', 'bytes', new Config());
    }

    public function test_delete_destroys_the_prefixed_public_id(): void
    {
        $upload = $this->createMock(UploadApi::class);
        $upload->expects($this->once())->method('destroy')
            ->with('guildforge/events/a', ['resource_type' => 'image'])
            ->willReturn(new ApiResponse(['result' => 'ok'], []));

        $this->adapter(upload: $upload)->delete('events/a.jpg');
    }

    public function test_delete_ignores_an_asset_cloudinary_does_not_have(): void
    {
        $upload = $this->createMock(UploadApi::class);
        $upload->expects($this->once())->method('destroy')->willReturn(new ApiResponse(['result' => 'not found'], []));

        $this->adapter(upload: $upload)->delete('events/a.jpg');
    }

    public function test_delete_ignores_a_not_found_error(): void
    {
        $upload = $this->createMock(UploadApi::class);
        $upload->expects($this->once())->method('destroy')->willThrowException(new NotFound('missing'));

        $this->adapter(upload: $upload)->delete('events/a.jpg');
    }

    public function test_delete_reports_any_other_error(): void
    {
        $upload = $this->createStub(UploadApi::class);
        $upload->method('destroy')->willThrowException(new RateLimited('slow down'));

        $this->expectException(UnableToDeleteFile::class);

        $this->adapter(upload: $upload)->delete('events/a.jpg');
    }

    public function test_delete_reports_an_unexpected_result(): void
    {
        $upload = $this->createStub(UploadApi::class);
        $upload->method('destroy')->willReturn(new ApiResponse(['result' => 'error'], []));

        $this->expectException(UnableToDeleteFile::class);

        $this->adapter(upload: $upload)->delete('events/a.jpg');
    }

    public function test_file_exists_asks_the_admin_api_for_the_prefixed_public_id(): void
    {
        $admin = $this->createMock(AdminApi::class);
        $admin->expects($this->once())->method('asset')
            ->with('guildforge/events/a', ['resource_type' => 'image'])
            ->willReturn(new ApiResponse(['public_id' => 'guildforge/events/a'], []));

        $this->assertTrue($this->adapter(admin: $admin)->fileExists('events/a.jpg'));
    }

    public function test_file_exists_is_false_for_an_asset_cloudinary_does_not_have(): void
    {
        $admin = $this->createStub(AdminApi::class);
        $admin->method('asset')->willThrowException(new NotFound('missing'));

        $this->assertFalse($this->adapter(admin: $admin)->fileExists('events/a.jpg'));
    }

    public function test_file_exists_assumes_the_file_is_there_when_the_admin_api_is_rate_limited(): void
    {
        $this->assertFileExistsFailsSafeOn(new RateLimited('slow down'));
    }

    public function test_file_exists_assumes_the_file_is_there_when_the_admin_api_fails(): void
    {
        $this->assertFileExistsFailsSafeOn(new ApiError('https://key:secret@api.cloudinary.com/v1_1/test-cloud failed'));
    }

    public function test_file_exists_assumes_the_file_is_there_when_the_transport_fails(): void
    {
        $this->assertFileExistsFailsSafeOn(new TransferException('cURL error 28 on https://key:secret@api.cloudinary.com'));
    }

    public function test_file_exists_lets_programming_errors_through(): void
    {
        Log::spy();
        $admin = $this->createStub(AdminApi::class);
        $admin->method('asset')->willThrowException(new TypeError('bad argument'));

        $this->expectException(TypeError::class);

        $this->adapter(admin: $admin)->fileExists('events/a.jpg');
    }

    public function test_size_and_last_modified_come_from_the_asset(): void
    {
        $admin = $this->createStub(AdminApi::class);
        $admin->method('asset')->willReturn(new ApiResponse(['bytes' => 2048, 'created_at' => '2026-01-15T10:00:00Z'], []));
        $adapter = $this->adapter(admin: $admin);

        $this->assertSame(2048, $adapter->fileSize('events/a.jpg')->fileSize());
        $this->assertSame(1768471200, $adapter->lastModified('events/a.jpg')->lastModified());
    }

    public function test_metadata_errors_are_reported_as_flysystem_errors(): void
    {
        $admin = $this->createStub(AdminApi::class);
        $admin->method('asset')->willThrowException(new RateLimited('slow down'));

        $this->expectException(UnableToRetrieveMetadata::class);

        $this->adapter(admin: $admin)->fileSize('events/a.jpg');
    }

    public function test_mime_type_comes_from_the_extension(): void
    {
        $this->assertSame('image/webp', $this->adapter()->mimeType('hero/slide.webp')->mimeType());
    }

    public function test_list_contents_follows_the_cursor(): void
    {
        $admin = $this->createMock(AdminApi::class);
        $admin->expects($this->exactly(2))->method('assets')->willReturnOnConsecutiveCalls(
            new ApiResponse(['resources' => [['public_id' => 'guildforge/events/a', 'bytes' => 1, 'created_at' => '2026-01-15T10:00:00Z']], 'next_cursor' => 'next'], []),
            new ApiResponse(['resources' => [['public_id' => 'guildforge/events/b', 'bytes' => 2, 'created_at' => '2026-01-15T10:00:00Z']]], []),
        );

        $files = iterator_to_array($this->adapter(admin: $admin)->listContents('events', true), false);

        $this->assertSame(['guildforge/events/a', 'guildforge/events/b'], array_map(static fn (StorageAttributes $file): string => $file->path(), $files));
    }

    public function test_urls_are_built_locally_without_the_admin_api(): void
    {
        $cloudinary = new class ('cloudinary://key:secret@test-cloud') extends Cloudinary {
            public function adminApi(): AdminApi
            {
                throw new LogicException('URLs must not call the Admin API');
            }
        };
        $adapter = new CloudinaryStorageAdapter($cloudinary, new CloudinaryPathMapper('guildforge'));

        $this->assertStringStartsWith('https://res.cloudinary.com/test-cloud/image/upload/v1/guildforge/events/a?', $adapter->getUrl('events/a.jpg'));
        $this->assertStringStartsWith('https://res.cloudinary.com/test-cloud/video/upload/v1/guildforge/videos/intro?', $adapter->getUrl('videos/intro.mp4'));
    }

    private function assertFileExistsFailsSafeOn(Throwable $error): void
    {
        Log::spy();
        $admin = $this->createStub(AdminApi::class);
        $admin->method('asset')->willThrowException($error);

        $this->assertTrue($this->adapter(admin: $admin)->fileExists('events/a.jpg'));

        Log::shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $context === ['path' => 'events/a.jpg', 'exception' => $error::class]
                && ! str_contains($message.json_encode($context), 'secret'),
        );
    }

    private function adapter(?UploadApi $upload = null, ?AdminApi $admin = null, ?ImageOptimizationServiceInterface $optimizer = null): CloudinaryStorageAdapter
    {
        $cloudinary = $this->createStub(Cloudinary::class);
        $cloudinary->method('uploadApi')->willReturn($upload ?? $this->createStub(UploadApi::class));
        $cloudinary->method('adminApi')->willReturn($admin ?? $this->createStub(AdminApi::class));

        return new CloudinaryStorageAdapter($cloudinary, new CloudinaryPathMapper('guildforge'), $optimizer);
    }
}
