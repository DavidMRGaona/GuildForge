<?php

declare(strict_types=1);

namespace Tests\Support\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Contracts\OffersActivityAttributes;
use App\Application\Activity\DTOs\ActivityAttributeDTO;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use App\Application\Activity\DTOs\ActivityContractChecks;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Domain\Activity\ValueObjects\ActivitySourceType;
use App\Domain\SiteBlocks\LinkTargetRule;
use Illuminate\Support\Str;

/**
 * What every activity source must do (core#774). Use it in the source's test (or extend
 * ActivitySourceTestCase) and say how to build the source and a subject that counts:
 *
 *     final class SessionActivitySourceTest extends ActivitySourceTestCase
 *     {
 *         protected function activitySource(): ActivitySourceInterface
 *         {
 *             return $this->app->make(SessionActivitySource::class);
 *         }
 *
 *         protected function activitySourceOwner(): string
 *         {
 *             return 'game-tables';
 *         }
 *
 *         protected function createCountedActivitySubject(): string
 *         {
 *             return $this->completedTableWithPlayers()->id;
 *         }
 *     }
 *
 * The test methods start with `test_activity_` so they never clash with the class's own.
 */
trait ActivitySourceContractTests
{
    /** The source under test, built from the container as the core builds it */
    abstract protected function activitySource(): ActivitySourceInterface;

    /** The module that registers the source (`core` for the core's own) */
    abstract protected function activitySourceOwner(): string;

    /** Creates a subject that counts, with at least one fact, and returns its id */
    abstract protected function createCountedActivitySubject(): string;

    /** An id no subject has; override it when the source's ids are not UUIDs */
    protected function unknownActivitySubjectId(): string
    {
        return (string) Str::uuid();
    }

    public function test_activity_source_type_belongs_to_its_owner(): void
    {
        $source = $this->activitySource();
        $type = ActivitySourceType::tryFrom($source->type());

        $this->assertInstanceOf(ActivitySourceType::class, $type, 'The type must be `<module>.<thing>`.');
        $this->assertSame($this->activitySourceOwner(), $type->owner(), 'The type must start with the module that registers it.');
        $this->assertNotSame('', trim($source->label()), 'The source needs a label.');
    }

    public function test_activity_source_is_registered_and_read_by_the_core(): void
    {
        $source = $this->activitySource();
        $registration = $this->app->make(ActivitySourceRegistryInterface::class)->find($source->type());

        $this->assertNotNull($registration, 'Return the class from registerActivitySources().');
        $this->assertSame($source->kind(), $registration->kind);

        $reader = $this->app->make(ActivityReaderInterface::class);
        $this->assertTrue($reader->facts($source->type(), $this->createCountedActivitySubject())->isRead(), 'The core could not read its facts: see the warning in the log.');
        $this->assertTrue($reader->history($source->type(), null, 10)->isRead(), 'The core could not read its history: see the warning in the log.');
    }

    public function test_activity_facts_of_an_unknown_subject_are_empty(): void
    {
        $this->assertSame([], $this->activitySource()->facts($this->unknownActivitySubjectId()));
    }

    public function test_activity_facts_of_a_counted_subject_are_valid(): void
    {
        $id = $this->createCountedActivitySubject();
        $source = $this->activitySource();

        // Throws on anything the core refuses: the same person twice in one role, a fact that is not a DTO
        $subject = new ActivitySubjectFactsDTO($id, $source->facts($id));

        $this->assertNotSame([], $subject->facts, 'A subject that counts has at least one fact.');

        if ($source instanceof OffersActivityAttributes) {
            $offered = array_map(static fn (ActivityAttributeDTO $attribute): string => $attribute->key, $source->attributes());

            foreach ($subject->facts as $fact) {
                foreach (array_keys($fact->attributes) as $key) {
                    $this->assertContains($key, $offered, "Attribute '{$key}' is not listed by attributes().");
                }
            }
        }
    }

    public function test_activity_history_walks_to_the_end_without_repeats(): void
    {
        $created = [$this->createCountedActivitySubject(), $this->createCountedActivitySubject(), $this->createCountedActivitySubject()];
        $source = $this->activitySource();
        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $page = $source->history($cursor, 2);
            $this->assertLessThanOrEqual(2, count($page->subjects), 'A page holds at most the limit asked.');
            $this->assertNotSame($cursor ?? '', $page->nextCursor, 'A page must never point back to itself.');

            foreach ($page->subjects as $subject) {
                $this->assertArrayNotHasKey($subject->subjectId, $seen, "Subject '{$subject->subjectId}' came twice.");
                $seen[$subject->subjectId] = true;
                $this->assertSame(
                    $this->comparableFacts($source->facts($subject->subjectId)),
                    $this->comparableFacts($subject->facts),
                    "History and facts() disagree on '{$subject->subjectId}'.",
                );
            }

            $cursor = $page->nextCursor;
            $this->assertLessThan(10_000, ++$pages, 'The history never ends.');
        } while ($cursor !== null);

        foreach ($created as $id) {
            $this->assertArrayHasKey($id, $seen, "Subject '{$id}' counts but the history does not list it.");
        }
    }

    public function test_activity_describe_answers_only_for_the_subjects_asked(): void
    {
        $id = $this->createCountedActivitySubject();
        $unknown = $this->unknownActivitySubjectId();

        $described = $this->activitySource()->describe([$id, $unknown], null);

        $this->assertArrayNotHasKey($unknown, $described);

        foreach ($described as $key => $subject) {
            $this->assertSame($id, (string) $key);
            // Whatever its PHPDoc says, a module may return anything
            $this->assertTrue(ActivityContractChecks::areAll([$subject], ActivitySubjectDTO::class), 'describe() returns ActivitySubjectDTO.');
            // A numeric id comes back as an int key: PHP turns it into one
            $this->assertSame((string) $key, $subject->subjectId);

            if ($subject->url !== null) {
                $this->assertNotNull(LinkTargetRule::normalize($subject->url, allowInsecure: true), 'A link is a path of the site or an https address.');
            }
        }
    }

    public function test_activity_attributes_are_valid(): void
    {
        $source = $this->activitySource();

        if (! $source instanceof OffersActivityAttributes) {
            $this->addToAssertionCount(1);

            return;
        }

        $attributes = $source->attributes();
        $this->assertTrue(ActivityContractChecks::isListOf($attributes, ActivityAttributeDTO::class), 'attributes() returns a list of ActivityAttributeDTO.');

        $keys = [];
        foreach ($attributes as $attribute) {
            $this->assertArrayNotHasKey($attribute->key, $keys, "Attribute '{$attribute->key}' is listed twice.");
            $keys[$attribute->key] = true;

            $ids = array_map(static fn (ActivityAttributeValueDTO $value): string => $value->id, $source->attributeValues($attribute->key));
            $this->assertSame(array_values(array_unique($ids)), $ids, "Attribute '{$attribute->key}' lists a value twice.");
        }

        $this->assertSame([], $source->attributeValues('no_such_attribute'));
    }

    /**
     * @param  list<ActivityFactDTO>  $facts
     * @return list<string>
     */
    private function comparableFacts(array $facts): array
    {
        $comparable = array_map(static fn (ActivityFactDTO $fact): string => json_encode([
            $fact->userId,
            $fact->role->value,
            $fact->occurredAt->getTimestamp(),
            $fact->placement,
            array_map(static fn (ActivityAttributeValueDTO $value): string => $value->id, $fact->attributes),
        ], JSON_THROW_ON_ERROR), $facts);
        sort($comparable);

        return $comparable;
    }
}
