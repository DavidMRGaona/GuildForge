<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Tests\Support\Authorization\CreatesUsersWithPermissions;

/**
 * Behaviour every Eloquent column source shares; the test class says how to build its records.
 */
trait ColumnSourceContractTests
{
    use CreatesUsersWithPermissions;

    abstract protected function source(): MediaUsageSourceInterface;

    abstract protected function makeRecord(?string $path): Model;

    abstract protected function updatePermission(): string;

    public function test_usages_lists_each_record_with_an_image(): void
    {
        $with = $this->makeRecord('x/2026/a.jpg');
        $this->makeRecord(null);
        $this->makeRecord('');

        $usages = iterator_to_array($this->source()->usages(), false);

        $this->assertCount(1, $usages);
        $this->assertSame('x/2026/a.jpg', $usages[0]->storedPath);
        $this->assertSame((string) $with->getKey(), $usages[0]->recordId);
        $this->assertNotSame('', $usages[0]->role);
    }

    public function test_usages_for_unknown_record_is_empty(): void
    {
        $this->assertSame([], $this->source()->usagesFor((string) Str::uuid()));
        $this->assertSame([], $this->source()->usagesFor('not-a-record'));
    }

    public function test_usages_for_returns_the_record_usage(): void
    {
        $record = $this->makeRecord('x/a.jpg');

        $usages = $this->source()->usagesFor((string) $record->getKey());

        $this->assertCount(1, $usages);
        $this->assertSame('x/a.jpg', $usages[0]->storedPath);
    }

    public function test_uses_file_matches_only_the_exact_id(): void
    {
        $this->makeRecord('events/a.jpg');
        $source = $this->source();

        $this->assertTrue($source->usesFile(new MediaFileId('events/a')));
        $this->assertFalse($source->usesFile(new MediaFileId('events/a2')));
        $this->assertFalse($source->usesFile(new MediaFileId('events/A')));
        $this->assertFalse($source->usesFile(new MediaFileId('events/_')));
        $this->assertFalse($source->usesFile(new MediaFileId('events/%')));
    }

    public function test_uses_file_finds_a_record_outside_the_claimed_folder(): void
    {
        $this->makeRecord('galleries/x.jpg');

        $this->assertTrue($this->source()->usesFile(new MediaFileId('galleries/x')));
    }

    public function test_uses_file_matches_a_stored_delivery_url(): void
    {
        $this->makeRecord('https://res.cloudinary.com/demo/image/upload/v12/guildforge/events/2026/a.jpg');

        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/2026/a')));
    }

    public function test_uses_file_matches_ids_containing_like_wildcards(): void
    {
        $this->makeRecord('events/my_file.jpg');
        $this->makeRecord('events/50%off.jpg');

        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/my_file')));
        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/50%off')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('events/myxfile')));
    }

    public function test_uses_file_matches_extensionless_and_encoded_delivery_urls(): void
    {
        $this->makeRecord('https://res.cloudinary.com/demo/image/upload/v1/guildforge/events/plain');
        $this->makeRecord('https://res.cloudinary.com/demo/image/upload/c_fill,w_300/guildforge/events/my%20photo.jpg');
        $this->makeRecord('events\\win\\back.jpg');

        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/plain')));
        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/my photo')));
        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/win/back')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('events/plai')));
    }

    public function test_edit_url_is_null_without_permission(): void
    {
        $record = $this->makeRecord('x/a.jpg');
        $id = (string) $record->getKey();

        $without = $this->editorWithPermissions([]);
        $with = $this->editorWithPermissions([$this->updatePermission()]);

        $this->assertNull($this->source()->editUrl($id, $without));
        $this->assertNotNull($this->source()->editUrl($id, $with));
        $this->assertNull($this->source()->editUrl((string) Str::uuid(), $with));
    }

    public function test_content_folder_is_excluded_from_the_claim(): void
    {
        $this->assertSame(['content/'], $this->source()->excludedSubfolders());
        $this->assertFalse($this->source()->hidden());
    }
}
