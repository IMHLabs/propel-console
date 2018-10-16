<?php

namespace PropelConsole\Tests\Generator\Command;

use Propel\Tests\TestCaseFixturesDatabase;
use PropelConsole\Generator\Command\VersionAddCommand;
use PropelConsole\Generator\Command\VersionRemoveCommand;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Application;
use Propel\Generator\Manager\MigrationManager;

/**
 * @group database
 */
class VersionTest extends TestCaseFixturesDatabase
{
    protected $connectionOption;

    public function setUp()
    {
        parent::setUp();
        $this->connectionOption =  ['migration_command=' . $this->getConnectionDsn('bookstore', true)];
        $this->connectionOption = str_replace('dbname=test', 'dbname=migration', $this->connectionOption);
    }

    public function testAddVersionCommand()
    {
        $app = new Application('Propel', Propel::VERSION);
        $command = new VersionAddCommand();
        $app->add($command);
		
		$timeStamp = time();
		
		$input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'version:add',
            '--migration-table' => 'propel_migration',
            '--timestamp' => $timeStamp,
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

        $this->assertEquals(0, $result, 'version:add tests failed');
    }
	
    public function testRemoveVersionCommand()
    {
        $app = new Application('Propel', Propel::VERSION);
        $command = new VersionRemoveCommand();
        $app->add($command);
		
		$timeStamp = time();
		
		$input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'version:remove',
            '--migration-table' => 'propel_migration',
            '--timestamp' => $timeStamp,
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

        $this->assertEquals(0, $result, 'version:remove failed');
    }
}
