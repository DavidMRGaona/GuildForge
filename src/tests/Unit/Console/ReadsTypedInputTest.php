<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Concerns\ReadsTypedInput;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

final class ReadsTypedInputTest extends TestCase
{
    /**
     * What the command reads from this input
     *
     * @param  array<string, mixed>  $input
     * @return array{string, bool, ?string, list<string>}
     */
    private function read(array $input): array
    {
        $command = new class extends Command
        {
            use ReadsTypedInput;

            protected $signature = 'typed:input {name?} {--flag} {--text=} {--many=*}';

            /** @return array{string, bool, ?string, list<string>} */
            public function read(): array
            {
                return [$this->stringArgument('name'), $this->flag('flag'), $this->stringOption('text'), $this->stringListOption('many')];
            }
        };
        $command->setLaravel($this->app);

        $arrayInput = new ArrayInput($input, $command->getDefinition());
        $command->setInput($arrayInput);
        $command->setOutput(new OutputStyle($arrayInput, new NullOutput));

        return $command->read();
    }

    public function test_it_reads_what_was_given_with_its_type(): void
    {
        $this->assertSame(
            ['tournaments', true, 'x', ['a.example', 'b.example']],
            $this->read(['name' => 'tournaments', '--flag' => true, '--text' => 'x', '--many' => ['a.example', 'b.example']]),
        );
    }

    public function test_missing_values_read_as_empty(): void
    {
        $this->assertSame(['', false, null, []], $this->read([]));
    }
}
