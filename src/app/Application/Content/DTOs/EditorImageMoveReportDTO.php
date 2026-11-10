<?php

declare(strict_types=1);

namespace App\Application\Content\DTOs;

final readonly class EditorImageMoveReportDTO
{
    /**
     * @param  int  $checked  Stored texts read (of the source asked for, or of every source)
     * @param  list<string>  $moved  "<value>: <local URL> → <Cloudinary path>" of every image moved (or that would move)
     * @param  list<string>  $broken  "<value>: <URL>" of local images that are missing or unsafe, left as they are
     * @param  list<string>  $failed  "<value>: <URL> (<reason>)" of images not moved, whose value and local copy are kept
     * @param  list<string>  $otherHosts  "<value>: <URL> (<whether the file is here>)" of "/storage/" URLs on hosts not passed, left as they are
     * @param  list<string>  $kept  "<local path> (<why>)" of moved files whose local copy stays: another text still names it, or deletions are held
     * @param  list<string>  $held  Why no local copy is deleted in this run (texts that cannot be read); empty when deletions may go ahead
     */
    public function __construct(
        public int $checked,
        public array $moved,
        public array $broken,
        public array $failed,
        public array $otherHosts = [],
        public array $kept = [],
        public array $held = [],
    ) {}
}
