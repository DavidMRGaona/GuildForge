<?php

declare(strict_types=1);

/*
 * Writes a PHPUnit configuration that runs one part of the suite, so CI can run the parts on
 * separate machines at the same time.
 *
 * Usage, from the host's src/ directory:
 *   php ../.github/scripts/phpunit-part.php <part> <parts> <output.xml>
 *
 * The test files are the ones PHPUnit itself finds with phpunit.xml (--list-test-files), so a new
 * test directory or suite is picked up without touching this script. They are sorted by path and
 * dealt out in turn: every part gets about as many files of each directory, and the same files
 * land in the same part on every machine. The output is phpunit.xml with its test suites replaced
 * by one suite listing that part's files; it is written next to phpunit.xml, whose paths are
 * relative to it. ../.github/scripts/check-phpunit-parts.sh checks that the parts together run
 * every test exactly once.
 */

function fail(string $message): never
{
    fwrite(STDERR, "::error::{$message}\n");
    exit(1);
}

$arguments = array_values(array_filter((array) ($_SERVER['argv'] ?? []), is_string(...)));
if (count($arguments) !== 4) {
    fail('Usage: php ../.github/scripts/phpunit-part.php <part> <parts> <output.xml>');
}
[, $partArgument, $partsArgument, $output] = $arguments;

$part = filter_var($partArgument, FILTER_VALIDATE_INT);
$parts = filter_var($partsArgument, FILTER_VALIDATE_INT);

if ($parts === false || $parts < 1 || $part === false || $part < 1 || $part > $parts) {
    fail("Invalid part '{$partArgument}' of '{$partsArgument}': it must be a number from 1 to the number of parts");
}

// A bare file name: the copy must sit next to phpunit.xml, and must never replace it
if (preg_match('/^[A-Za-z0-9._-]+\.xml$/', $output) !== 1 || $output === 'phpunit.xml' || $output === 'phpunit.xml.dist') {
    fail("Invalid output '{$output}': it must be a file name ending in .xml, other than phpunit.xml");
}

$root = getcwd();
if ($root === false || ! is_file($root.'/phpunit.xml') || ! is_file($root.'/vendor/bin/phpunit')) {
    fail('Run it from the directory that holds phpunit.xml and vendor/, with the Composer dependencies installed');
}

// Xdebug in develop mode makes PHPUnit stop with a warning before listing anything
$process = proc_open(
    [PHP_BINARY, 'vendor/bin/phpunit', '--configuration=phpunit.xml', '--list-test-files'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root,
    ['XDEBUG_MODE' => 'off'] + getenv(),
);
if (! is_resource($process)) {
    fail('Could not run PHPUnit to list the test files');
}
$listing = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);

if ($status !== 0 || $listing === false) {
    fail("PHPUnit could not list the test files (exit {$status}): ".trim((string) $errors));
}

$files = [];
foreach (explode("\n", $listing) as $line) {
    if (! str_starts_with($line, ' - ')) {
        continue;
    }
    $path = substr($line, 3);
    if (! str_starts_with($path, $root.'/')) {
        fail("PHPUnit listed a test file outside {$root}: {$path}");
    }
    $files[] = substr($path, strlen($root) + 1);
}

$files = array_values(array_unique($files));
if ($files === []) {
    fail('PHPUnit listed no test files');
}
sort($files, SORT_STRING);

$selected = [];
foreach ($files as $index => $file) {
    if ($index % $parts === $part - 1) {
        $selected[] = $file;
    }
}
if ($selected === []) {
    fail("Part {$part} of {$parts} has no test files: there are only ".count($files));
}

$config = new DOMDocument;
$config->preserveWhiteSpace = false;
$config->formatOutput = true;
if (! $config->load($root.'/phpunit.xml')) {
    fail('Could not read phpunit.xml');
}

$suitesList = $config->getElementsByTagName('testsuites');
$suites = $suitesList->item(0);
if ($suitesList->length !== 1 || $suites === null) {
    fail("phpunit.xml must have exactly one <testsuites> element, it has {$suitesList->length}");
}
while ($suites->firstChild !== null) {
    $suites->removeChild($suites->firstChild);
}

$suite = $config->createElement('testsuite');
$suite->setAttribute('name', "Part {$part} of {$parts}");
foreach ($selected as $file) {
    $entry = $config->createElement('file');
    $entry->appendChild($config->createTextNode($file));
    $suite->appendChild($entry);
}
$suites->appendChild($suite);

if ($config->save($root.'/'.$output) === false) {
    fail("Could not write {$output}");
}

echo "Part {$part} of {$parts}: ".count($selected).' of '.count($files)." test files, in {$output}\n";
