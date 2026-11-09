<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs;

use InvalidArgumentException;

/**
 * The one thing a mail is about, as a person can mute it: «No recibir más avisos de «<label>»».
 *
 * The type and the id are stored with the person's choice and put in a signed link, so they must
 * stay the same for as long as the thing exists. The names are stored as they were when the
 * person muted it, so the profile can still show them once the thing (or its module) is gone.
 */
final readonly class MailSubjectDTO
{
    /** "<module>.<kind>", like mail category keys, e.g. "game-tables.campaign". */
    public const string TYPE_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*\.[a-z0-9]+(?:-[a-z0-9]+)*';

    public const int TYPE_MAX_LENGTH = 100;

    /** A UUID, a number or a slug. */
    public const string ID_PATTERN = '[A-Za-z0-9_-]{1,64}';

    /** Translation keys only ("my-module::mail.subjects.campaign"), never free text. */
    public const string TYPE_LABEL_PATTERN = '[A-Za-z0-9_.:-]{1,150}';

    public const int LABEL_MAX_LENGTH = 160;

    /** Visible name, on one line and at most LABEL_MAX_LENGTH characters. */
    public string $label;

    /**
     * @param  string  $type  Kind of thing, "<module>.<kind>".
     * @param  string  $id  Identifier of the thing within its type.
     * @param  string  $label  Its name as people see it (written by people: it is escaped wherever it is shown).
     * @param  string|null  $typeLabel  Translation key of the kind («Campaña»), shown before the name in the profile.
     *
     * @throws InvalidArgumentException When something could not be stored or put in a link.
     */
    public function __construct(
        public string $type,
        public string $id,
        string $label,
        public ?string $typeLabel = null,
    ) {
        if (strlen($type) > self::TYPE_MAX_LENGTH || preg_match('/^'.self::TYPE_PATTERN.'$/', $type) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid mail subject type "%s".', $type));
        }

        if (preg_match('/^'.self::ID_PATTERN.'$/', $id) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid mail subject id "%s".', $id));
        }

        if ($typeLabel !== null && preg_match('/^'.self::TYPE_LABEL_PATTERN.'$/', $typeLabel) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid mail subject type label "%s".', $typeLabel));
        }

        $this->label = $this->normalizeLabel($label);
    }

    private function normalizeLabel(string $label): string
    {
        // One line, no control or invisible formatting characters
        $oneLine = preg_replace(['/\s+/u', '/[\p{Cc}\p{Cf}]/u'], [' ', ''], $label);

        if ($oneLine === null) {
            throw new InvalidArgumentException('The mail subject name is not valid UTF-8.');
        }

        $normalized = trim(mb_substr(trim($oneLine), 0, self::LABEL_MAX_LENGTH));

        if ($normalized === '') {
            throw new InvalidArgumentException('The mail subject name is empty.');
        }

        return $normalized;
    }
}
