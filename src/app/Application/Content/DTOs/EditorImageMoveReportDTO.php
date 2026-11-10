<?php

declare(strict_types=1);

namespace App\Application\Content\DTOs;

final readonly class EditorImageMoveReportDTO
{
    /**
     * @param  int  $checked  Stored values read
     * @param  list<string>  $moved  "<value>: <local URL> → <Cloudinary path>" of every image moved (or that would move)
     * @param  list<string>  $broken  "<value>: <URL>" of local images that are missing or unsafe, left as they are
     * @param  list<string>  $failed  "<value>: <URL> (<reason>)" of images not moved, whose value and local copy are kept
     */
    public function __construct(
        public int $checked,
        public array $moved,
        public array $broken,
        public array $failed,
    ) {}
}
