<?php

declare(strict_types=1);

return [
    'errors' => [
        'unsupported_type' => 'The file is not a supported spreadsheet. Upload a .csv or an .xlsx file.',
        'unreadable' => 'The file could not be read. Check that it is not damaged or save it again as .csv or .xlsx.',
        'empty' => 'The file has no row with data below the header.',
        'too_many_rows' => 'The file has more than :limit rows with data.',
        'too_many_columns' => 'The file has more than :limit columns.',
        'too_large' => 'The file is larger than :limit.',
        'duplicate_headers' => 'Column :column has the same name as another one. Each header column needs a different name.',
    ],
];
