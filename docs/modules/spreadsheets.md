# Spreadsheets

Modules have no Composer dependencies of their own, so the core reads and writes spreadsheets for them behind two contracts: one reads an uploaded `.csv` or `.xlsx` into rows of text, the other writes a one-sheet `.xlsx` template people can fill in. Use them for imports (members, attendees, a catalogue) and their templates. For the rest of the module SDK, see the [module development guide](./README.md).

`openspout/openspout` and `league/csv` are also installed because Filament brings them, but nothing guarantees they stay: never use them from a module.

Requirement: set `requires.core` in `module.json` to the core release that introduces the contracts (the one with core#815). An older core has no binding for them.

## Reading a file

Resolve `App\Application\Spreadsheets\SpreadsheetReaderInterface` and call `read()` with a local path and the limits you accept:

```php
use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Application\Spreadsheets\SpreadsheetReadErrorMessage;
use App\Application\Spreadsheets\SpreadsheetReaderInterface;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;

try {
    $sheet = app(SpreadsheetReaderInterface::class)->read(
        $upload->getRealPath(),
        new SpreadsheetLimitsDTO(maxRows: 1000, maxBytes: 2 * 1024 * 1024),
    );
} catch (SpreadsheetReadException $exception) {
    // $exception->reason is a SpreadsheetReadFailure; the message below is translated
    throw ValidationException::withMessages(['file' => SpreadsheetReadErrorMessage::for($exception)]);
}

foreach ($sheet->rows as $row) {
    $row->number;            // 2, 3, 5... as a spreadsheet program shows it
    $row->value('Email');    // '' when the cell is empty or missing
}
```

`SpreadsheetDTO` carries:

| Field | Content |
|-------|---------|
| `format` | `SpreadsheetFormat::Csv` or `SpreadsheetFormat::Xlsx`, decided by the content |
| `headers` | The first row with text, each header trimmed, in column order. Columns without a header are left out, with whatever their cells hold |
| `rows` | At least one `SpreadsheetRowDTO`: `number` (the row in the file, header included, so error messages can say «fila 5») and `values` (header → text, every header present) |

Blank rows, and rows with text only under columns without a header, are skipped but still counted, so the numbers stay the file's.

### What arrives as text

Every value is a trimmed string (non-breaking and zero-width spaces included), so a module never guesses:

| Cell | Text |
|------|------|
| `.xlsx` date | `2024-03-05`; with a time, `2024-03-05 18:30:00` (wall time as stored, no time zone conversion) |
| `.xlsx` number | A dot and no thousands separator, whatever its format: `1234.5`, `17`, `0.00001` (14 significant digits) |
| `.xlsx` number stored as text | As written: `0042` keeps its zeros |
| `.xlsx` boolean | `1` or `0` |
| `.xlsx` formula | Its stored result. A formula whose result is an error arrives as its formula (openspout cannot tell it from a text starting with `=`) |
| `.xlsx` error (`#N/A`) | `''` |
| `.csv` cell | As written: `25,50` stays `25,50`. Parse numbers and dates yourself |

Only the first sheet of an `.xlsx` is read; the others are ignored.

### What the reader accepts

- **Type by content, never by extension**: an Office Open XML workbook (a ZIP with `[Content_Types].xml` and `xl/workbook.xml`, so `.xlsx` and `.xlsm`) or a text. An `.xlsx` named `.csv` is read as `.xlsx`. Old `.xls` files, `.ods`, `.docx`, images, PDF and any text with control characters other than tab and line breaks are `unsupported_type`.
- **CSV encoding**: UTF-8 with or without BOM; when the content is not valid UTF-8, Windows-1252 (what Excel in Spanish exports). UTF-16 («Texto Unicode» in Excel) is not supported: it holds NUL bytes, so it is `unsupported_type`.
- **CSV separator**: `;`, `,` or a tab, whichever the header row uses most outside quotes (`;` first on a tie; `,` when it has none). Quoted fields may hold separators and line breaks.

### Limits

`SpreadsheetLimitsDTO` (every limit at least 1, otherwise `InvalidArgumentException`):

| Limit | Default | Counts |
|-------|---------|--------|
| `maxRows` | `1000` | Rows with data below the header |
| `maxColumns` | `50` | Width of the header and of every row, up to its last cell with text |
| `maxBytes` | 5 MB | Size of the file. An `.xlsx` may also take at most 30 times that once uncompressed (`XlsxPreflight::MAX_EXPANSION`), against ZIP bombs |

Rows are streamed: reaching a limit stops reading at once, without loading the rest of the file. A CSV is read whole once to tell its encoding, which `maxBytes` bounds.

openspout builds each row whole, and fills in every empty cell up to the row's last cell, before the reader sees it. So, before opening the file, the reader also refuses:

- an `.xlsx` whose entries inflate past that multiple, counting the bytes really inflated (an archive can declare smaller sizes than it holds), as `too_large`;
- a CSV record or an `.xlsx` row reaching past column 16384 (`XlsxPreflight::MAX_SHEET_COLUMNS`, the last one Excel and LibreOffice have, or `maxColumns` if larger), with its empty fields, as `too_many_columns`;

and, while reading, stops as `too_large` once openspout has built more cells, empty ones included, than `maxBytes` (at least 100000, `OpenSpoutSpreadsheetReader::MIN_CELL_BUDGET`): empty cells far to the right of every row would otherwise cost time without limit.

### Failures

`SpreadsheetReadException` has a closed `reason` (`App\Domain\Spreadsheets\SpreadsheetReadFailure`), plus `limit` (the limit passed, for the `too_*` reasons) and `column` (1-based, for `duplicate_headers`):

| Reason | When |
|--------|------|
| `unsupported_type` | Not a CSV text or an Office Open XML workbook |
| `unreadable` | Missing, not a file, damaged, or a Windows-1252 byte that does not exist |
| `empty` | No header, or no row with data below it |
| `too_many_rows` | More than `maxRows` data rows |
| `too_many_columns` | A header or row wider than `maxColumns`, or a record past the last spreadsheet column |
| `too_large` | Over `maxBytes`, an `.xlsx` that expands too much, or more cells than the budget |
| `duplicate_headers` | Two headers equal ignoring case, accents and spacing (`Teléfono` and `TELEFONO`) |

Neither the message nor a previous exception carries the file's content: log the `reason`, never the rows. `SpreadsheetReadErrorMessage::for($exception)` gives the person a translated message (`lang/*/spreadsheets.php`) with the limit (`2 MB`, `1000`) or the column number.

### Finding columns

`App\Application\Spreadsheets\SpreadsheetHeader::normalise()` is how the reader compares headers: lower case, without accents, with spaces collapsed (`« Fecha  de ALTA»` → `fecha de alta`). Use it to find a module's columns whatever the person wrote:

```php
$columns = [];

foreach ($sheet->headers as $header) {
    $columns[SpreadsheetHeader::normalise($header)] = $header;
}

$email = $row->value($columns[SpreadsheetHeader::normalise(__('members::import.columns.email'))] ?? '');
```

## Writing a template

Resolve `App\Application\Spreadsheets\SpreadsheetWriterInterface` and pass a `SpreadsheetTemplateDTO`; `write()` returns the `.xlsx` bytes:

```php
use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use App\Application\Spreadsheets\SpreadsheetWriterInterface;

$bytes = app(SpreadsheetWriterInterface::class)->write(new SpreadsheetTemplateDTO(
    headers: ['Nombre', 'Email', 'Fecha de alta', 'Cuota'],
    rows: [['Ana Pérez', 'ana@example.org', '2025-01-31', 25.5]],
    sheetName: 'Socios',
));

return response()->streamDownload(fn () => print($bytes), 'plantilla-socios.xlsx', [
    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
]);
```

- `headers`: non-blank texts, different from each other by `SpreadsheetHeader::normalise()`.
- `rows`: optional example rows, each a list no wider than the header, of texts, ints, floats or `null` (an empty cell).
- `sheetName`: `Hoja1` by default; 1 to 31 characters, without `\ / ? * [ ] :` and not starting or ending with `'`.

Anything else throws `InvalidArgumentException` when the DTO is built. The header is bold and stays in view while scrolling, and each column is as wide as its longest text (10 to 60 characters). Texts are always written as text, never as formulas, so a value starting with `=` is safe. Write dates as `YYYY-MM-DD` texts: the reader gives them back unchanged. `SpreadsheetWriteException` means the temporary file could not be written.

## Testing

The core's tests use the fixtures in `src/tests/Fixtures/spreadsheets/` (CSV in UTF-8 with BOM and in Windows-1252 with `;`, a tab-separated file, an `.xlsx` with dates, numbers, a formula and a second sheet, an `.xlsx` named `.csv`, repeated headers, a Word document, an old `.xls`, a broken `.xlsx`, blank and header-only files). A module test can build its own `.xlsx` with the writer, save it to a temporary file and read it back through the container, or write a CSV string with `file_put_contents()`. Use small limits (`new SpreadsheetLimitsDTO(maxRows: 2)`) to test the limits without large files.
