# Spreadsheets

Modules have no Composer dependencies of their own, so the core reads and writes spreadsheets for them behind two contracts: one reads an uploaded `.csv` or `.xlsx` into rows of text, the other writes a one-sheet `.xlsx` template people can fill in. Use them for imports (members, attendees, a catalogue) and their templates. For the rest of the module SDK, see the [module development guide](./README.md).

`openspout/openspout` and `league/csv` are also installed because Filament brings them, but nothing guarantees they stay: never use them from a module.

Requirement: set `requires.core` in `module.json` to the core release that introduces the contracts (the one with core#815). An older core has no binding for them.

## Reading a file

Resolve `App\Application\Spreadsheets\Services\SpreadsheetReaderInterface` and call `read()` with a local path and the limits you accept:

```php
use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Application\Spreadsheets\Services\SpreadsheetReaderInterface;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use Illuminate\Validation\ValidationException;

try {
    $sheet = app(SpreadsheetReaderInterface::class)->read(
        $upload->getRealPath(),
        new SpreadsheetLimitsDTO(maxRows: 1000, maxBytes: 2 * 1024 * 1024),
    );
} catch (SpreadsheetReadException $exception) {
    // $exception->reason is a SpreadsheetReadFailure; userMessage() is translated
    throw ValidationException::withMessages(['file' => $exception->userMessage()]);
}

foreach ($sheet->rows as $row) {
    $row->number;            // 2, 3, 5... as a spreadsheet program shows it
    $row->value('Email');    // '' when the cell is empty, null when the file has no such header
}
```

`SpreadsheetDTO` carries:

| Field | Content |
|-------|---------|
| `format` | `SpreadsheetFormat::Csv` or `SpreadsheetFormat::Xlsx`, decided by the content |
| `headers` | The first row with text, each header trimmed, in column order. Columns without a header are left out, with whatever their cells hold |
| `rows` | At least one `SpreadsheetRowDTO`: `number` (the row in the file, header included, so error messages can say «fila 5») and `values` (header → text, every header present) |

Blank rows, and rows with text only under columns without a header, are skipped but still counted, so the numbers stay the file's.

PHP turns a numeric header (`2024`) into an `int` key of `values`: look values up with `value('2024')`, which finds both.

### What arrives as text

Every value is a trimmed string (non-breaking and zero-width spaces included), so a module never guesses:

| Cell | Text |
|------|------|
| `.xlsx` date | `2024-03-05`; with a time, `2024-03-05 18:30:00` (wall time as stored, no time zone conversion) |
| `.xlsx` time without a date | `18:30:00` |
| `.xlsx` number | A dot and no thousands separator, whatever its format: `1234.5`, `17`, `0.00001` (14 significant digits) |
| `.xlsx` number stored as text | As written: `0042` keeps its zeros |
| `.xlsx` boolean | `1` or `0` |
| `.xlsx` formula | Its stored result, as Excel and LibreOffice save it. A formula without a stored result (files written by some libraries) arrives as `0`; one whose result is an error arrives as its formula (`=1/0`), since openspout cannot tell it from a text starting with `=` |
| `.xlsx` error (`#N/A`) | `''` |
| `.csv` cell | As written: `25,50` stays `25,50`. Parse numbers and dates yourself |

Only the first sheet of an `.xlsx` is read; the others are ignored. In a workbook with the 1904 date system (old Excel for Mac), a time without a date that falls exactly on midnight arrives as `1904-01-01`.

### What the reader accepts

- **Type by content, never by extension**: an Office Open XML workbook (a ZIP with `[Content_Types].xml` and `xl/workbook.xml`, so `.xlsx` and `.xlsm`) or a text. An `.xlsx` named `.csv` is read as `.xlsx`. Old `.xls` files, `.ods`, `.docx`, images, PDF and any text with control characters other than tab and line breaks are `unsupported_type`.
- **CSV encoding**: UTF-8 with or without BOM when the whole text is valid UTF-8; otherwise the whole text is read as Windows-1252 (what Excel in Spanish exports). So a UTF-8 file with a single stray Latin-1 byte is read as Windows-1252 throughout, and its UTF-8 accents arrive garbled (`JosÃ©`): ask people to save it again as «CSV UTF-8». A text with bytes Windows-1252 does not define (`0x81`, `0x8D`, `0x8F`, `0x90`, `0x9D`) cannot be decoded either way, so it is `unreadable`. UTF-16 («Texto Unicode» in Excel) holds NUL bytes, so it is `unsupported_type`.
- **CSV separator**: the one Excel's first line `sep=;` names (that line is left out and is not a row); otherwise `;`, `,` or a tab, the first in that order that splits the header into several fields and each of the next nine records into as many; otherwise the one giving the header most fields; otherwise `,`. So `Nombre;Dirección (calle, número, piso)` is read with `;`.
- **CSV quotes** (RFC 4180, split by the core, not by openspout): a field is quoted only when it starts with `"`; inside, `""` is one quote, and separators and line breaks are text; a quote in the middle of an unquoted field is text.
- **CSV line ends**: CRLF, LF or CR alone. A final end-of-file mark (`\x1A`, left by old programs) is ignored.

### Limits

`SpreadsheetLimitsDTO` (every limit at least 1, otherwise `InvalidArgumentException`):

| Limit | Default | Counts |
|-------|---------|--------|
| `maxRows` | `1000` | Rows with data below the header |
| `maxColumns` | `50` | Width of the header and of every row, up to its last cell with text |
| `maxBytes` | 5 MB | Size of the file. An `.xlsx` may also take at most 30 times that once uncompressed, against ZIP bombs |

Rows are streamed: reaching `maxRows` or `maxColumns` stops reading at that row, without loading the rest of the file. A CSV is read whole once to tell its encoding, which `maxBytes` bounds.

openspout builds each `.xlsx` row whole, filling in every empty cell up to the row's last one, and every empty row up to the next row number, before the reader sees it. So the reader also bounds what can be built:

- **Before opening an `.xlsx`**, it inflates every entry of the archive once, counting the bytes really inflated (an archive can declare smaller sizes than it holds), and refuses entries that inflate past 30 times `maxBytes` (`too_large`). Then it reads, as openspout reads them, the parts openspout parses: every sheet the workbook points to whatever its name, everything in `xl/worksheets/` and the shared strings. It refuses a row reaching past column 16384 (the last one Excel and LibreOffice have, or `maxColumns` if larger) by a cell reference or by its number of cells (`too_many_columns`), a row number past 1048576, the last spreadsheet row (`too_many_rows`), and a part with more elements than 16 per cell of the budget below (`too_large`). Relation targets are taken as openspout takes them, literally under `xl/`; a target with `.`, `..` or doubled slashes (no spreadsheet program writes one) could point this check and openspout at different entries, so it is `unreadable`. Anything it cannot read this way is `unreadable` too. Other parts only count for the inflated bytes.
- **While splitting a CSV**, a record with more fields than that same column cap is `too_many_columns`, before it grows further.
- **While reading either**, the cells built, empty ones included and at least one per row (blank rows and blank CSV lines too), may not pass 4 × (`maxRows` + 1) × `maxColumns`, and never less than 10000 (200200 with the defaults). Past it, reading stops as `too_many_columns` when the row that crossed it is wider than `maxColumns`, otherwise as `too_many_rows`. So a single cell far down, say in row 1048576, gives `too_many_rows` even when the file has few rows with data: the empty rows before it count.

Each read and write works in a temporary folder of its own (shared strings caches, the parts of a workbook being written), deleted when it ends, also when it fails.

### Failures

`SpreadsheetReadException` has a closed `reason` (`App\Domain\Spreadsheets\SpreadsheetReadFailure`), plus `limit` (the limit passed, for the `too_*` reasons) and `column` (1-based, for `duplicate_headers`):

| Reason | When |
|--------|------|
| `unsupported_type` | Not a CSV text or an Office Open XML workbook |
| `unreadable` | Missing, not a file, damaged, any part of a workbook that cannot be read, or a CSV with bytes Windows-1252 does not define |
| `empty` | No header, or no row with data below it |
| `too_many_rows` | More than `maxRows` data rows, a row number past the last spreadsheet row, or too many blank rows for the cell budget |
| `too_many_columns` | A header or row wider than `maxColumns`, a row past the last spreadsheet column, or rows of empty cells too wide for the cell budget |
| `too_large` | Over `maxBytes`, an `.xlsx` that inflates past 30 times it, or a sheet or the shared strings with more elements than the limits can need |
| `duplicate_headers` | Two headers equal ignoring case, accents and spacing (`Teléfono` and `TELEFONO`) |

Neither the message nor a previous exception carries the file's content: log the `reason`, never the rows. `$exception->userMessage()` gives the person a translated message (`lang/*/spreadsheets.php`) with the limit (`2 MB`, `1000`) or the column number.

### Finding columns

`App\Application\Spreadsheets\SpreadsheetHeader::normalise()` is how the reader compares headers: lower case, without accents, with spaces collapsed (`« Fecha  de ALTA»` → `fecha de alta`). Use it to find a module's columns whatever the person wrote:

```php
$columns = [];

foreach ($sheet->headers as $header) {
    $columns[SpreadsheetHeader::normalise($header)] = $header;
}

$email = $row->value($columns[SpreadsheetHeader::normalise(__('members::import.columns.email'))] ?? '') ?? '';
```

## Writing a template

Resolve `App\Application\Spreadsheets\Services\SpreadsheetWriterInterface` and pass a `SpreadsheetTemplateDTO`; `write()` returns the `.xlsx` bytes:

```php
use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use App\Application\Spreadsheets\Services\SpreadsheetWriterInterface;

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
- `rows`: optional example rows, each a list no wider than the header, of texts, ints, finite floats or `null` (an empty cell).
- Every text (headers included) in UTF-8 and at most 32767 characters, what a cell holds.
- `sheetName`: `Hoja1` by default; UTF-8, 1 to 31 characters, without `\ / ? * [ ] :` and not starting or ending with `'`.

Anything else throws `InvalidArgumentException` when the DTO is built. The header is bold and stays in view while scrolling, and each column is as wide as its longest text (10 to 60 characters). Texts are always written as text, never as formulas, so a value starting with `=` is safe. Write dates as `YYYY-MM-DD` texts: the reader gives them back unchanged. `SpreadsheetWriteException` means the temporary folder could not be created or written, or openspout failed.

## Testing

The core's tests use the fixtures in `src/tests/Fixtures/spreadsheets/` (CSV in UTF-8 with BOM and in Windows-1252 with `;`, a tab-separated file, an `.xlsx` with dates, numbers, a formula with its stored result and a second sheet, an `.xlsx` named `.csv`, repeated headers, a Word document, an old `.xls`, a broken `.xlsx`, blank and header-only files), kept byte for byte by their `.gitattributes`. A module test can build its own `.xlsx` with the writer, save it to a temporary file and read it back through the container, or write a CSV string with `file_put_contents()`. Use small limits (`new SpreadsheetLimitsDTO(maxRows: 2)`) to test the limits without large files.
