<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Spreadsheets\SpreadsheetReaderInterface;
use App\Application\Spreadsheets\SpreadsheetWriterInterface;
use App\Infrastructure\Spreadsheets\OpenSpoutSpreadsheetReader;
use App\Infrastructure\Spreadsheets\OpenSpoutSpreadsheetWriter;
use Illuminate\Support\ServiceProvider;

/**
 * Spreadsheet reading and writing for modules (core#815). Both are stateless and build their
 * openspout reader or writer on every call.
 */
final class SpreadsheetsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SpreadsheetReaderInterface::class => OpenSpoutSpreadsheetReader::class,
        SpreadsheetWriterInterface::class => OpenSpoutSpreadsheetWriter::class,
    ];
}
