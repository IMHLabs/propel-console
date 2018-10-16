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
class DataMigrationDownCommand extends AbstractCommand
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
                'data-migration:down'
            )
            ->setAliases(
                [
                    'data-down'
                ]
            )
            ->setDescription(
                'Execute data migrations down'
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

        $previousTimestamps = $manager->getAlreadyExecutedMigrationTimestamps();
        $nextMigrationTimestamp = array_pop($previousTimestamps);
        if (!$nextMigrationTimestamp) {
            $output->writeln('No migration were ever executed on this database - nothing to reverse.');

            return false;
        }

        $output->writeln(
            sprintf(
                'Executing migration %s down',
                $manager->getMigrationClassName($nextMigrationTimestamp)
            )
        );

        $nbPreviousTimestamps = count($previousTimestamps);
        if ($nbPreviousTimestamps) {
            $previousTimestamp = array_pop($previousTimestamps);
        } else {
            $previousTimestamp = 0;
        }

        $migration = $manager->getMigrationObject($nextMigrationTimestamp);


        if (!$input->getOption('fake')) {
            if (false === $migration->preDown($manager)) {
                if ($input->getOption('force')) {
                    $output->writeln('<error>preDown() returned false. Continue migration.</error>');
                } else {
                    $output->writeln('<error>preDown() returned false. Aborting migration.</error>');

                    return false;
                }
            }
        }

        foreach ($migration->getDownSQL() as $datasource => $sql) {
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

                $output->writeln(
                    sprintf(
                        '%d of %d SQL statements executed successfully on datasource "%s"',
                        $res,
                        count($statements),
                        $datasource
                    )
                );
            }

            $manager->removeMigrationTimestamp($datasource, $nextMigrationTimestamp);

            if ($input->getOption('verbose')) {
                $output->writeln(
                    sprintf(
                        'Downgraded migration date to %d for datasource "%s"',
                        $previousTimestamp,
                        $datasource
                    )
                );
            }
        }

        if (!$input->getOption('fake')) {
            $migration->postDown($manager);
        }

        if ($nbPreviousTimestamps) {
            $output->writeln(
                sprintf(
                    'Reverse migration complete. %d more migrations available for reverse.',
                    $nbPreviousTimestamps
                )
            );
        } else {
            $output->writeln('Reverse migration complete. No more migration available for reverse');
        }
    }
}
