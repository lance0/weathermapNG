<?php

// The standalone PHPUnit suite ships neither symfony/console nor
// illuminate/console (composer requires only illuminate/database|log|support).
// DiscoverCommand extends Illuminate\Console\Command, so provide a minimal,
// class_exists()-guarded stand-in for that parent chain so the command can be
// instantiated and its handle() exercised deterministically, mirroring the
// SettingsTestStubUser pattern already used in tests/bootstrap.php. In a real
// LibreNMS environment these classes already exist and the stubs are skipped.
namespace Symfony\Component\Console\Command {
    if (!class_exists('Symfony\Component\Console\Command\Command')) {
        class Command
        {
            public const SUCCESS = 0;
            public const FAILURE = 1;
        }
    }
}

namespace Illuminate\Console {
    if (!class_exists('Illuminate\Console\Command')) {
        class Command extends \Symfony\Component\Console\Command\Command
        {
            public function __construct()
            {
                // no-op: DiscoverCommand::__construct() calls parent::__construct();
            }

            /** @var object|null input double exposing getArgument()/getOption() */
            protected $wmngInput;

            /** @var object|null output double capturing writeln() lines */
            protected $wmngOutput;

            public function setIO(object $input, object $output): void
            {
                $this->wmngInput = $input;
                $this->wmngOutput = $output;
            }

            public function argument(string $key)
            {
                return $this->wmngInput->getArgument($key);
            }

            public function option(string $key)
            {
                return $this->wmngInput->getOption($key);
            }

            public function info(string $message): void
            {
                $this->wmngOutput->writeln("<info>{$message}</info>");
            }

            public function error(string $message): void
            {
                $this->wmngOutput->writeln("<error>{$message}</error>");
            }

            public function warn(string $message): void
            {
                $this->wmngOutput->writeln("<comment>{$message}</comment>");
            }
        }
    }
}

namespace LibreNMS\Plugins\WeathermapNG\Tests {

use LibreNMS\Plugins\WeathermapNG\Console\Commands\DiscoverCommand;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService;
use PHPUnit\Framework\TestCase;

    /**
     * Minimal argument/option store mirroring the surface handle() reads.
     */
    class DiscoverCommandInput
    {
        private array $args;
        private array $opts;

        public function __construct(array $args = [], array $opts = [])
        {
            $this->args = $args;
            $this->opts = $opts;
        }

        public function getArgument(string $key)
        {
            return $this->args[$key] ?? null;
        }

        public function getOption(string $key)
        {
            return $this->opts[$key] ?? null;
        }
    }

    /**
     * Captures written output lines for assertions.
     */
    class DiscoverCommandOutput
    {
        public array $lines = [];

        public function writeln(string $line): void
        {
            $this->lines[] = $line;
        }

        public function all(): string
        {
            return implode("\n", $this->lines);
        }
    }

    /**
     * A DiscoverCommand whose map lookup is stubbed so no DB is touched.
     */
    class TestableDiscoverCommand extends DiscoverCommand
    {
        public $resolveMapResult;
        public $resolveMapShouldThrow = false;

        protected function resolveMap(int $mapId): Map
        {
            if ($this->resolveMapShouldThrow) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }

            return $this->resolveMapResult;
        }
    }

    /**
     * A discovery service that returns a canned flat counts array.
     */
    class StubDiscoveryService extends AutoDiscoveryService
    {
        public array $result = ['nodes_added' => 0, 'links_added' => 0];

        public function __construct()
        {
            // no-op: avoid parent constructor side effects
        }

        public function discoverAndSeedMap(Map $map, array $params): array
        {
            return $this->result;
        }
    }

    class DiscoverCommandTest extends TestCase
    {
        private TestableDiscoverCommand $command;
        private StubDiscoveryService $service;
        private DiscoverCommandInput $input;
        private DiscoverCommandOutput $output;

        protected function setUp(): void
        {
            $this->service = new StubDiscoveryService();
            $this->command = new TestableDiscoverCommand($this->service);
            $this->input = new DiscoverCommandInput(
                ['map_id' => '42'],
                ['os' => ['iosxe', 'ios']]
            );
            $this->output = new DiscoverCommandOutput();
            $this->command->setIO($this->input, $this->output);

            $map = new Map(['name' => 'Backbone']);
            $this->command->resolveMapResult = $map;
        }

        public function test_command_returns_success_when_map_found(): void
        {
            $this->service->result = ['nodes_added' => 2, 'links_added' => 3];

            $exit = $this->command->handle();

            $this->assertSame(DiscoverCommand::SUCCESS, $exit);
            $this->assertStringContainsString(
                'Discovery complete: 2 nodes, 3 links added to map Backbone.',
                $this->output->all()
            );
        }

        public function test_zero_counts_render_correctly(): void
        {
            $this->service->result = ['nodes_added' => 0, 'links_added' => 0];

            $exit = $this->command->handle();

            $this->assertSame(DiscoverCommand::SUCCESS, $exit);
            $this->assertStringContainsString(
                'Discovery complete: 0 nodes, 0 links added to map Backbone.',
                $this->output->all()
            );
        }

        public function test_counts_default_to_zero_when_keys_missing(): void
        {
            $this->service->result = [];

            $exit = $this->command->handle();

            $this->assertSame(DiscoverCommand::SUCCESS, $exit);
            $this->assertStringContainsString(
                'Discovery complete: 0 nodes, 0 links added to map Backbone.',
                $this->output->all()
            );
        }

        public function test_command_returns_failure_when_map_missing(): void
        {
            $this->command->resolveMapShouldThrow = true;

            $exit = $this->command->handle();

            $this->assertSame(DiscoverCommand::FAILURE, $exit);
            $this->assertStringContainsString('Map with ID 42 not found.', $this->output->all());
            $this->assertStringNotContainsString('Discovery complete', $this->output->all());
        }

        public function test_os_filters_flow_into_validated_params(): void
        {
            $captured = null;
            $this->service->result = ['nodes_added' => 1, 'links_added' => 1];

            $proxy = new class($this->service) extends StubDiscoveryService {
                public $captured;
                public function validateDiscoveryParams(array $params): array
                {
                    $this->captured = $params;

                    return parent::validateDiscoveryParams($params);
                }
            };

            $command = new TestableDiscoverCommand($proxy);
            $command->setIO($this->input, $this->output);
            $command->resolveMapResult = new Map(['name' => 'Backbone']);

            $command->handle();

            $this->assertSame(['min_degree' => 0, 'os' => 'iosxe,ios'], $proxy->captured);
        }
    }
}