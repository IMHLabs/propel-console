<?php
/**
 * Propel Extended Console Commands
 *
 * Library to provide extended propel console commands
 *
 * PHP Version: 5
 *
 * @category  InMotion
 * @package   PropelConsole\Generator\Command
 * @author    IMH Development <development@inmotionhosting.com>
 * @copyright 2018 Copyright (c) InMotion Hosting
 * @license   https://inmotionhosting.com proprietary
 * @link      https://inmotionhosting.com
 */
namespace PropelConsole\Generator\Command;

use Propel\Generator\Command\AbstractCommand;
use Propel\Runtime\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PropelConsole\Generator\Manager\DataMigrationManager as MigrationManager;
use Propel\Generator\Util\SqlParser;
use PropelConfig\Configuration;

/**
 * Propel Extended Console Commands
 *
 * Library to provide extended propel console commands
 *
 * PHP Version: 5
 *
 * @category  InMotion
 * @package   PropelConsole\Generator\Command
 * @author    IMH Development <development@inmotionhosting.com>
 * @copyright 2018 Copyright (c) InMotion Hosting
 * @license   https://inmotionhosting.com proprietary
 * @link      https://inmotionhosting.com
 */
class VersionAddCommand extends AbstractCommand
{
    /**
     * Configure Command
     *
     * @return null
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->addOption(
                'config-file',
                null,
                InputOption::VALUE_REQUIRED,
                'The configuration file to use.'
            )
            ->addOption(
                'namespace',
                null,
                InputOption::VALUE_REQUIRED,
                'The namespace if using the config-file option.'
            )
            ->addOption(
                'timestamp',
                null,
                InputOption::VALUE_REQUIRED,
                'Migration timestamp to add'
            )
            ->addOption(
                'migration-table',
                null,
                InputOption::VALUE_REQUIRED,
                'Migration table name'
            )
            ->addOption(
                'connection',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Connection to use',
                []
            )
            ->addOption(
                'fake',
                null,
                InputOption::VALUE_NONE,
                'Does not touch the actual schema,
                but marks previous migration as executed.'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Continues with the migration even when errors occur.'
            )
            ->setName(
                'version:add'
            )
            ->setAliases(
                [
                    'version-add'
                ]
            )
            ->setDescription(
                'Add version timestamp to migration table'
            );
    }

    /**
     * Execute Command
     *
     * @param InputInterface  $input  Command Input
     * @param OutputInterface $output OutputInterface
     *
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (($this->hasInputOption('config-file', $input))
         && ($this->hasInputOption('namespace', $input))) {
            $config = new Configuration(
                [
                    'configFile' => $input->getOption('config-file')
                ]
            );
            $connectionConfig = $config->getConnectionConfig($input->getOption('namespace'));
            $migrationConfig = $config->getMigrationConfig($input->getOption('namespace'));
            $input->setOption('config-dir', $migrationConfig['config_path']);
            $input->setOption(
                'connection',
                sprintf(
                    '%s=%s',
                    $input->getOption('namespace'),
                    $config->getDsn($input->getOption('namespace'))
                )
            );
        }
        $configOptions = [];

        $generatorConfig = $this->getGeneratorConfig($configOptions, $input);

        $manager = new MigrationManager();
        $manager->setGeneratorConfig($generatorConfig);
        
        $connections = [];
        $optionConnections = $input->getOption('connection');
        if (!$optionConnections) {
            $connections = $generatorConfig->getBuildConnections();
        } else {
            foreach ($optionConnections as $connection) {
                list($name, $dsn, $infos) = $this->parseConnection($connection);
                $connections[$name] = array_merge(['dsn' => $dsn], $infos);
            }
        }

        $manager->setConnections($connections);
        if ($this->hasInputOption('migration-table', $input)) {
            $manager->setMigrationTable($input->getOption('migration-table'));
        } else {
            $manager->setMigrationTable('propel_migration');
        }

        $output->writeln(
            sprintf(
                'Adding migration %s',
                $input->getOption('timestamp')
            )
        );
        foreach ($connections as $datasource => $connection) {
            $manager->updateLatestMigrationTimestamp(
                $datasource,
                $input->getOption('timestamp')
            );
        }
    }
}
