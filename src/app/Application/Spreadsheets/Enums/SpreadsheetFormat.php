<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets\Enums;

enum SpreadsheetFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
}
