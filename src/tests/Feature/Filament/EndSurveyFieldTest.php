<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface;
use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\DTOs\EndSurveyOptionDTO;
use App\Application\Surveys\Enums\EndSurveyChoiceOutcome;
use App\Filament\Forms\Components\EndSurveyField;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Surveys\FakeSubjectEndSurveyChoice;
use Tests\TestCase;

/**
 * The field a module puts in a subject's form: it shows what the surveys module offers, saves
 * the choice once the subject has an id, and never stops the subject from being saved. The core
 * event form is the bench, through the extension point modules use.
 */
final class EndSurveyFieldTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        $this->actingAs($this->admin);
        EventResource::clearExtendedFormSections();
    }

    protected function tearDown(): void
    {
        EventResource::clearExtendedFormSections();

        parent::tearDown();
    }

    public function test_without_a_surveys_module_the_field_is_hidden(): void
    {
        $this->addField();

        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])
            ->assertFormFieldIsHidden('end_survey')
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_it_shows_the_options_and_fills_the_current_choice(): void
    {
        $fake = $this->fake($this->choice(selected: 'template:2'));
        $event = $this->event();

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertFormFieldIsVisible('end_survey')
            ->assertFormSet(['end_survey' => 'template:2'])
            ->assertSee('Plantilla del tipo: Valoración')
            ->assertSee('Torneo de ejemplo');

        $this->assertSame(['sourceType' => 'example-thing', 'subjectId' => (string) $event->id, 'userId' => (string) $this->admin->id], $fake->asked[0]);
    }

    public function test_creating_a_subject_saves_the_choice_with_its_new_id(): void
    {
        $fake = $this->fake($this->choice());
        $category = TagModel::factory()->forEvents()->create();

        Livewire::test(CreateEvent::class)
            ->assertFormSet(['end_survey' => 'type-default'])
            ->fillForm([
                'title' => 'Jornada de ejemplo',
                'slug' => 'jornada-de-ejemplo',
                'description' => '<p>Una jornada.</p>',
                'start_date' => '2026-12-05 10:00:00',
                'end_date' => '2026-12-05 20:00:00',
                'category_id' => $category->id,
                'end_survey' => 'template:2',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = EventModel::query()->where('slug', 'jornada-de-ejemplo')->firstOrFail();
        $this->assertCount(1, $fake->chosen);
        $this->assertSame('example-thing', $fake->chosen[0]['sourceType']);
        $this->assertSame((string) $event->id, $fake->chosen[0]['subjectId']);
        $this->assertSame('template:2', $fake->chosen[0]['selection']->value);
        $this->assertNull($fake->chosen[0]['selection']->baseTemplate);
        $this->assertSame((string) $this->admin->id, $fake->chosen[0]['userId']);
        $this->assertNull($fake->asked[0]['subjectId']);
    }

    public function test_an_unchanged_choice_is_not_saved_again(): void
    {
        $fake = $this->fake($this->choice(selected: 'template:2'));

        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])
            ->fillForm(['title' => 'Otro título'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $fake->chosen);
    }

    public function test_an_own_survey_asks_which_template_to_start_from(): void
    {
        $fake = $this->fake($this->choice());

        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])
            ->assertFormFieldIsHidden('end_survey_base')
            ->fillForm(['end_survey' => 'own'])
            ->assertFormFieldIsVisible('end_survey_base')
            ->assertFormSet(['end_survey_base' => 'template:1'])
            ->fillForm(['end_survey_base' => 'template:2'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('own', $fake->chosen[0]['selection']->value);
        $this->assertSame('template:2', $fake->chosen[0]['selection']->baseTemplate);
    }

    public function test_a_locked_choice_is_shown_but_never_saved(): void
    {
        $fake = $this->fake($this->choice(
            selected: 'template:2',
            locked: true,
            lockedReason: 'Ya se ha enviado.',
            editUrl: 'https://example.com/encuestas/1/editar',
        ));

        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])
            ->assertFormFieldIsDisabled('end_survey')
            ->assertSee('Ya se ha enviado.')
            ->assertSee(__('filament.end_survey.view'))
            ->assertDontSee(__('filament.end_survey.edit'))
            ->assertSee('https://example.com/encuestas/1/editar')
            ->set('data.end_survey', 'none')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $fake->chosen);
    }

    public function test_the_edit_link_shows_when_there_is_a_survey_to_edit(): void
    {
        $this->fake($this->choice(selected: 'own', editUrl: 'https://example.com/encuestas/1/editar'));

        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])
            ->assertSee(__('filament.end_survey.edit'))
            ->assertSee('https://example.com/encuestas/1/editar');
    }

    public function test_a_person_who_may_not_choose_still_saves_the_subject(): void
    {
        $fake = $this->fake($this->choice(), failure: new AuthorizationException);
        $event = $this->event();

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['title' => 'Título nuevo', 'end_survey' => 'none'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('filament.end_survey.not_allowed'));

        $this->assertSame('Título nuevo', $event->fresh()?->title);
        $this->assertSame([], $fake->chosen);
    }

    public function test_a_survey_set_aside_as_a_draft_is_announced(): void
    {
        $this->fake($this->choice(), outcome: EndSurveyChoiceOutcome::ReleasedAsDraft);

        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])
            ->fillForm(['end_survey' => 'none'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('filament.end_survey.released_as_draft'));
    }

    public function test_a_choice_locked_while_saving_is_announced(): void
    {
        $this->fake($this->choice(), outcome: EndSurveyChoiceOutcome::Locked);

        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])
            ->fillForm(['end_survey' => 'none'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('filament.end_survey.locked'));
    }

    public function test_the_subject_type_can_depend_on_the_form(): void
    {
        $fake = new FakeSubjectEndSurveyChoice($this->choice());
        $this->app->instance(SubjectEndSurveyChoiceInterface::class, $fake);
        $this->addField(EndSurveyField::forSubject(fn (Get $get): string => $get('is_published') === true ? 'published-thing' : 'example-thing'));

        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])
            ->fillForm(['is_published' => true, 'end_survey' => 'none'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('published-thing', $fake->chosen[0]['sourceType']);
    }

    private function fake(EndSurveyChoiceDTO $choice, EndSurveyChoiceOutcome $outcome = EndSurveyChoiceOutcome::Saved, ?AuthorizationException $failure = null): FakeSubjectEndSurveyChoice
    {
        $fake = new FakeSubjectEndSurveyChoice($choice, $outcome, $failure);
        $this->app->instance(SubjectEndSurveyChoiceInterface::class, $fake);
        $this->addField();

        return $fake;
    }

    private function addField(?Group $field = null): void
    {
        EventResource::extendFormSections([
            Section::make('Encuesta')->schema([$field ?? EndSurveyField::forSubject('example-thing')]),
        ]);
    }

    private function choice(
        string $selected = 'type-default',
        bool $locked = false,
        ?string $lockedReason = null,
        ?string $editUrl = null,
    ): EndSurveyChoiceDTO {
        return new EndSurveyChoiceDTO(
            options: [
                new EndSurveyOptionDTO('type-default', 'Plantilla del tipo: Valoración'),
                new EndSurveyOptionDTO('template:2', 'Torneo de ejemplo', 'Otras plantillas'),
                new EndSurveyOptionDTO('own', 'Una encuesta propia'),
                new EndSurveyOptionDTO('none', 'Ninguna'),
            ],
            selected: $selected,
            ownValue: 'own',
            baseTemplates: [
                new EndSurveyOptionDTO('template:1', 'Valoración'),
                new EndSurveyOptionDTO('template:2', 'Torneo de ejemplo'),
            ],
            locked: $locked,
            lockedReason: $lockedReason,
            editUrl: $editUrl,
        );
    }

    private function event(): EventModel
    {
        $event = EventModel::factory()->create([
            'start_date' => now()->addDays(10)->setTime(10, 0),
            'end_date' => now()->addDays(10)->setTime(20, 0),
        ]);
        $event->tags()->attach(TagModel::factory()->forEvents()->create());

        return $event;
    }
}
