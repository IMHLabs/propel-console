<?php

namespace PropelConsole\Tests\Generator\Command;

use PropelConsole\Generator\Command\CreateDataMigrationCommand;
use PropelConsole\Generator\Command\DataMigrationUpCommand;
use PropelConsole\Generator\Command\DataMigrationDownCommand;
use PropelConsole\Generator\Command\DataMigrationMigrateCommand;
use PropelConsole\Generator\Command\DumpSchemaCommand;
use Propel\Runtime\Propel;
use Propel\Tests\TestCaseFixturesDatabase;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;

/**
 * @group database
 */
class DataMigrationTest extends TestCaseFixturesDatabase
{
    protected $connectionOption;
    protected $configDir;
    protected $schemaDir;
    protected $outputDir;

    public function setUp()
    {
        parent::setUp();
        $finder = new Finder();
        $this->connectionOption =  ['migration_command=' . $this->getConnectionDsn('bookstore', true)];
        $this->connectionOption = str_replace('dbname=test', 'dbname=migration', $this->connectionOption);
        $finder->directories()->name('vendor')->in(__DIR__.'/../../../../..')->depth(0);
        if ($finder->count()) {
            foreach ($finder as $file) {
                $propelTestDir = realpath(__DIR__.'/../../../../../vendor/propel/propel/tests');
            }
        } else {
            $finder->directories()->name('vendor')->in(__DIR__.'/../../../../../..')->depth(0);
            if ($finder->count()) {
                foreach ($finder as $file) {
                    $propelTestDir = realpath(__DIR__.'/../../../../../vendor/propel/propel/tests');
                }
            }
        }
        $this->configDir = $propelTestDir . '/Fixtures/migration-command';
        $this->schemaDir = $propelTestDir . '/Fixtures/migration-command';
        $this->outputDir = __DIR__ . '/../../../../migrationdiff';
    }

    public function tearDown()
    {
        parent::tearDown();
        $files = glob($this->outputDir . '/PropelDataMigration_*.php');
        foreach ($files as $file) {
            unlink($file);
        }
    }
        
    public function testCreateCommand()
    {
        $app = new Application('Propel', Propel::VERSION);
        $command = new CreateDataMigrationCommand();
        $app->add($command);
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'migration:create-data-migration',
            '--schema-dir' => $this->schemaDir,
            '--config-dir' => $this->configDir,
            '--output-dir' => $this->outputDir,
            '--platform' => ucfirst($this->getDriver()) . 'Platform',
            '--connection' => $this->connectionOption,
            '--verbose' => true
        ]);
        $output = new \Symfony\Component\Console\Output\StreamOutput(fopen("php://temp", 'r+'));
        $app->setAutoExit(false);
        $result = $app->run($input, $output);

        if (0 !== $result) {
            rewind($output->getStream());
            echo stream_get_contents($output->getStream());
        }

        $this->assertEquals(0, $result, 'migration:create-data-migration failed');
        
        $files = glob($this->outputDir . '/PropelDataMigration_*.php');
        $this->assertGreaterThanOrEqual(1, count($files));
    }

    public function testCreateCommandUsingSuffix()
    {
        $app = new Application('Propel', Propel::VERSION);
        $command = new CreateDataMigrationCommand();
        $app->add($command);

        $files = glob($this->outputDir . '/PropelDataMigration_*.php');
        foreach ($files as $file) {
            unlink($file);
        }

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'migration:create-data-migration',
            '--schema-dir' => $this->schemaDir,
            '--config-dir' => $this->configDir,
            '--output-dir' => $this->outputDir,
            '--platform' => ucfirst($this->getDriver()) . 'Platform',
            '--connection' => $this->connectionOption,
            '--suffix' => 'an_explanatory_filename_suffix',
            '--verbose' => true
        ]);

        $output = new \Symfony\Component\Console\Output\StreamOutput(fopen("php://temp", 'r+'));
        $app->setAutoExit(false);
        $result = $app->run($input, $output);

        if (0 !== $result) {
            rewind($output->getStream());
            echo stream_get_contents($output->getStream());
        }

        $this->assertEquals(0, $result, 'migration:create-data-migration failed');
        
        $files = glob($this->outputDir . '/PropelDataMigration_*_an_explanatory_filename_suffix.php');
        $this->assertGreaterThanOrEqual(1, count($files));
    }

    public function testUpCommand()
    {
		$this->testCreateCommand();
        $app = new Application('Propel', Propel::VERSION);
        $command = new DataMigrationUpCommand();
        $app->add($command);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'data-migration:up',
            '--config-dir' => $this->configDir,
            '--output-dir' => $this->outputDir,
            '--platform' => ucfirst($this->getDriver()) . 'Platform',
            '--connection' => $this->connectionOption,
            '--verbose' => true
        ]);

        $output = new \Symfony\Component\Console\Output\StreamOutput(fopen("php://temp", 'r+'));
        $app->setAutoExit(false);
        $result = $app->run($input, $output);

        rewind($output->getStream());
        if (0 !== $result) {
            echo stream_get_contents($output->getStream());
        }

        $this->assertEquals(0, $result, 'data-migration:up failed');
        $outputString = stream_get_contents($output->getStream());
        $this->assertContains('Migration complete.', $outputString);
    }

    public function testDownCommand()
    {
        $this->testCreateCommand();
        
        // Execute Up Migration
        $app = new Application('Propel', Propel::VERSION);
        $command = new DataMigrationUpCommand();
        $app->add($command);
        
        $input = new \Symfony\Component\Console\Input\ArrayInput([
                        'command' => 'data-migration:up',
                        '--config-dir' => $this->configDir,
                        '--output-dir' => $this->outputDir,
                        '--platform' => ucfirst($this->getDriver()) . 'Platform',
                        '--connection' => $this->connectionOption,
                        '--verbose' => true
        ]);
        
        $output = new \Symfony\Component\Console\Output\StreamOutput(fopen("php://temp", 'r+'));
        $app->setAutoExit(false);
        $result = $app->run($input, $output);
        
        $app = new Application('Propel', Propel::VERSION);
        $command = new DataMigrationDownCommand();
        $app->add($command);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'data-migration:down',
            '--config-dir' => $this->configDir,
            '--output-dir' => $this->outputDir,
            '--platform' => ucfirst($this->getDriver()) . 'Platform',
            '--connection' => $this->connectionOption,
            '--verbose' => true
        ]);

        $output = new \Symfony\Component\Console\Output\StreamOutput(fopen("php://temp", 'r+'));
        $app->setAutoExit(false);
        $result = $app->run($input, $output);

        rewind($output->getStream());
        if (0 !== $result) {
            echo stream_get_contents($output->getStream());
        }

        $this->assertEquals(0, $result, 'data-migration:down failed');
        $outputString = stream_get_contents($output->getStream());
        $this->assertContains('Reverse migration complete.', $outputString);
    }

    public function testMigrateCommand()
    {
        $this->testCreateCommand();
        $app = new Application('Propel', Propel::VERSION);
        $command = new DataMigrationMigrateCommand();
        $app->add($command);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'migration:data-migrate',
            '--config-dir' => $this->configDir,
            '--output-dir' => $this->outputDir,
            '--platform' => ucfirst($this->getDriver()) . 'Platform',
            '--connection' => $this->connectionOption,
            '--verbose' => true
        ]);

        $output = new \Symfony\Component\Console\Output\StreamOutput(fopen("php://temp", 'r+'));
        $app->setAutoExit(false);
        $result = $app->run($input, $output);

        rewind($output->getStream());
        if (0 !== $result) {
            echo stream_get_contents($output->getStream());
        }

        $this->assertEquals(0, $result, 'migration:data-migrate failed');
        $outputString = stream_get_contents($output->getStream());
        $this->assertContains('Migration complete.', $outputString);

        //revert this migration change so we have the same database structure as before this test
        $this->testDownCommand();
    }

    public function testDumpSchemaCommand()
    {
        $app = new Application('Propel', Propel::VERSION);
        $command = new DumpSchemaCommand();
        $app->add($command);
        $files = glob($this->outputDir . '/PropelMigration_*.php');
        foreach ($files as $file) {
            unlink($file);
        }
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'migration:dump-schema',
            '--schema-dir' => $this->schemaDir,
            '--config-dir' => $this->configDir,
            '--output-dir' => $this->outputDir,
            '--platform' => ucfirst($this->getDriver()) . 'Platform',
            '--connection' => $this->connectionOption,
            '--verbose' => true
        ]);
        $output = new \Symfony\Component\Console\Output\StreamOutput(fopen("php://temp", 'r+'));
        $app->setAutoExit(false);
        $result = $app->run($input, $output);
		
        if (0 !== $result) {
            rewind($output->getStream());
            echo stream_get_contents($output->getStream());
        }

        $this->assertEquals(0, $result, 'migration:dump-schema tests exited successfully');

        $files = glob($this->outputDir . '/PropelMigration_*.php');
        $this->assertGreaterThanOrEqual(1, count($files));
		$file = $files[0];

		$content = file_get_contents($file);
        $this->assertContains('CREATE TABLE ', $content);
    }
}
