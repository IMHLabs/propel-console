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
class DataMigrationUpCommand extends AbstractCommand
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
                'output-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'The output directory'
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
                'data-migration:up'
            )
            ->setAliases(
                [
                    'data-up'
                ]
            )
            ->setDescription(
                'Execute data migrations up'
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
            $input->setOption('output-dir', $migrationConfig['migration_path']);
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
        if ($this->hasInputOption('output-dir', $input)) {
            $configOptions['propel']['paths']['migrationDir'] = $input->getOption('output-dir');
        }
        
        $generatorConfig = $this->getGeneratorConfig($configOptions, $input);

        $this->createDirectory($generatorConfig->getSection('paths')['migrationDir']);

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
        $manager->setMigrationTable('propel_data_migration');
        $manager->setWorkingDirectory($generatorConfig->getSection('paths')['migrationDir']);

        $nextMigrationTimestamp = $manager->getFirstUpMigrationTimestamp();
        if (!$nextMigrationTimestamp) {
            $output->writeln('All migrations were already executed - nothing to migrate.');

            return false;
        }

        if ($input->getOption('fake')) {
            $output->writeln(
                sprintf(
                    'Faking migration %s up',
                    $manager->getMigrationClassName($nextMigrationTimestamp)
                )
            );
        } else {
            $output->writeln(
                sprintf(
                    'Executing migration %s up',
                    $manager->getMigrationClassName($nextMigrationTimestamp)
                )
            );
        }

        $migration = $manager->getMigrationObject($nextMigrationTimestamp);

        if (!$input->getOption('fake')) {
            if (false === $migration->preUp($manager)) {
                if ($input->getOption('force')) {
                    $output->writeln('<error>preUp() returned false. Continue migration.</error>');
                } else {
                    $output->writeln('<error>preUp() returned false. Aborting migration.</error>');
                    return false;
                }
            }
        }

        foreach ($migration->getUpSQL() as $datasource => $sql) {
            $connection = $manager->getConnection($datasource);

            if ($input->getOption('verbose')) {
                $output->writeln(
                    sprintf(
                        'Connecting to database "%s" using DSN "%s"',
                        $datasource,
                        $connection['dsn']
                    )
                );
            }

            $conn = $manager->getAdapterConnection($datasource);
            $res = 0;
            $statements = SqlParser::parseString($sql);

            if (!$input->getOption('fake')) {
                foreach ($statements as $statement) {
                    try {
                        if ($input->getOption('verbose')) {
                            $output->writeln(sprintf('Executing statement "%s"', $statement));
                        }

                        $conn->exec($statement);
                        $res++;
                    } catch (\Exception $e) {
                        if ($input->getOption('force')) {
                            //continue, but print error message
                            $output->writeln(
                                sprintf('<error>Failed to execute SQL "%s". Continue migration.</error>', $statement)
                            );
                        } else {
                            throw new RuntimeException(
                                sprintf('<error>Failed to execute SQL "%s". Aborting migration.</error>', $statement),
                                0,
                                $e
                            );
                        }
                    }
                }

                //make sure foreign_keys are activated again in mysql

                $output->writeln(
                    sprintf(
                        '%d of %d SQL statements executed successfully on datasource "%s"',
                        $res,
                        count($statements),
                        $datasource
                    )
                );
            }

            $manager->updateLatestMigrationTimestamp($datasource, $nextMigrationTimestamp);

            if ($input->getOption('verbose')) {
                $output->writeln(
                    sprintf(
                        'Updated latest migration date to %d for datasource "%s"',
                        $nextMigrationTimestamp,
                        $datasource
                    )
                );
            }
        }

        if (!$input->getOption('fake')) {
            $migration->postUp($manager);
        }

        $timestamps = $manager->getValidMigrationTimestamps();
        if ($timestamps) {
            $output->writeln(
                sprintf(
                    'Migration complete. %d migrations left to execute.',
                    count($timestamps)
                )
            );
        } else {
            $output->writeln('Migration complete. No further migration to execute.');
        }
    }
}
