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
use Propel\Generator\Manager\MigrationManager;
use Propel\Generator\Manager\ModelManager;
use Propel\Generator\Manager\SqlManager;
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
class DatabaseUpdateCommand extends AbstractCommand
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
                'mysql-engine',
                null,
                InputOption::VALUE_REQUIRED,
                'MySQL engine (MyISAM, InnoDB, ...)'
            )
            ->addOption(
                'schema-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'The directory where the schema files are placed'
            )
            ->addOption(
                'output-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'The output directory'
            )
            ->addOption(
                'sql-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'The sql output directory'
            )
            ->addOption(
                'migration-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'The migration output directory'
            )
            ->addOption(
                'migration-table',
                null,
                InputOption::VALUE_REQUIRED,
                'Migration table name'
            )
            ->addOption(
                'object-class',
                null,
                InputOption::VALUE_REQUIRED,
                'The object class generator name'
            )
            ->addOption(
                'object-stub-class',
                null,
                InputOption::VALUE_REQUIRED,
                'The object stub class generator name'
            )
            ->addOption(
                'object-multiextend-class',
                null,
                InputOption::VALUE_REQUIRED,
                'The object multiextend class generator name'
            )
            ->addOption(
                'query-class',
                null,
                InputOption::VALUE_REQUIRED,
                'The query class generator name'
            )
            ->addOption(
                'query-stub-class',
                null,
                InputOption::VALUE_REQUIRED,
                'The query stub class generator name'
            )
            ->addOption(
                'query-inheritance-class',
                null,
                InputOption::VALUE_REQUIRED,
                'The query inheritance class generator name'
            )
            ->addOption(
                'query-inheritance-stub-class',
                null,
                InputOption::VALUE_REQUIRED,
                'The query inheritance stub class generator name'
            )
            ->addOption(
                'tablemap-class',
                null,
                InputOption::VALUE_REQUIRED,
                'The tablemap class generator name'
            )
            ->addOption(
                'pluralizer-class',
                null,
                InputOption::VALUE_REQUIRED,
                'The pluralizer class name'
            )
            ->addOption(
                'enable-identifier-quoting',
                null,
                InputOption::VALUE_NONE,
                'Identifier quoting may result in undesired behavior'
            )
            ->addOption(
                'target-package',
                null,
                InputOption::VALUE_REQUIRED,
                '',
                ''
            )
            ->addOption(
                'disable-package-object-model',
                null,
                InputOption::VALUE_NONE,
                'Disable schema database merging (packageObjectModel)'
            )
            ->addOption(
                'disable-namespace-auto-package',
                null,
                InputOption::VALUE_NONE,
                'Disable namespace auto-packaging'
            )
            ->addOption(
                'composer-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory in which your composer.json resides',
                null
            )
            ->addOption(
                'validate',
                null,
                InputOption::VALUE_NONE,
                ''
            )
            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                ''
            )
            ->addOption(
                'connection',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Connection to use',
                []
            )
            ->addOption(
                'schema-name',
                null,
                InputOption::VALUE_REQUIRED,
                'The schema name for RDBMS supporting them',
                ''
            )
            ->addOption(
                'table-prefix',
                null,
                InputOption::VALUE_REQUIRED,
                'Add a prefix to all the table names in the database'
            )
            ->setName(
                'database:update'
            )
            ->setAliases(
                [
                    'database-update'
                ]
            )
            ->setDescription(
                'Perform build, build-sql and migrate commands.'
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
            $input->setOption('schema-dir', $migrationConfig['schema_path']);
            $input->setOption('output-dir', $migrationConfig['class_path']);
            $input->setOption('sql-dir', $migrationConfig['sql_path']);
            $input->setOption('migration-dir', $migrationConfig['migration_path']);
            $input->setOption('schema-name', $input->getOption('namespace'));
            $input->setOption(
                'connection',
                sprintf(
                    '%s=%s',
                    $input->getOption('namespace'),
                    $config->getDsn($input->getOption('namespace'))
                )
            );
        }
        $this->migrationBuild($input, $output);
        $this->sqlBuild($input, $output);
        $this->executeMigrations($input, $output);
    }
    
    /**
     * Build migration
     *
     * @param InputInterface  $input  Command Input
     * @param OutputInterface $output OutputInterface
     *
     * @return null
     */
    protected function migrationBuild(InputInterface $input, OutputInterface $output)
    {
        $configOptions = [];
        $inputOptions = $input->getOptions();

        foreach ($inputOptions as $key => $option) {
            if (null !== $option) {
                switch ($key) {
                    case 'schema-dir':
                        $configOptions['propel']['paths']['schemaDir'] = $option;
                        break;
                    case 'output-dir':
                        $configOptions['propel']['paths']['phpDir'] = $option;
                        break;
                    case 'object-class':
                        $configOptions['propel']['generator']['objectModel']['builders']['object'] = $option;
                        break;
                    case 'object-stub-class':
                        $configOptions['propel']['generator']['objectModel']['builders']['objectstub'] = $option;
                        break;
                    case 'object-multiextend-class':
                        $configOptions['propel']['generator']['objectModel']['builders']['objectmultiextend'] = $option;
                        break;
                    case 'query-class':
                        $configOptions['propel']['generator']['objectModel']['builders']['query'] = $option;
                        break;
                    case 'query-stub-class':
                        $configOptions['propel']['generator']['objectModel']['builders']['querystub'] = $option;
                        break;
                    case 'query-inheritance-class':
                        $configOptions['propel']['generator']['objectModel']['builders']['queryinheritance'] = $option;
                        break;
                    case 'query-inheritance-stub-class':
                        $configOptions['propel']['generator']['objectModel']['builders']['queryinheritancestub'] = $option;
                        break;
                    case 'tablemap-class':
                        $configOptions['propel']['generator']['objectModel']['builders']['tablemap'] = $option;
                        break;
                    case 'pluralizer-class':
                        $configOptions['propel']['generator']['objectModel']['pluralizerClass'] = $option;
                        break;
                    case 'composer-dir':
                        $configOptions['propel']['paths']['composerDir'] = $option;
                        break;
                    case 'disable-package-object-model':
                        if ($option) {
                            $configOptions['propel']['generator']['packageObjectModel'] = false;
                        }
                        break;
                    case 'disable-namespace-auto-package':
                        if ($option) {
                            $configOptions['propel']['generator']['namespaceAutoPackage'] = false;
                        }
                        break;
                    case 'mysql-engine':
                        $configOptions['propel']['database']['adapters']['mysql']['tableType'] = $option;
                        break;
                }
            }
        }
        $input->setOption('disable-namespace-auto-package', '1');
        $configOptions['propel']['generator']['namespaceAutoPackage'] = false;

        $generatorConfig = $this->getGeneratorConfig($configOptions, $input);

        $this->createDirectory($generatorConfig->getSection('paths')['phpDir']);

        $manager = new ModelManager();
        $manager->setFilesystem($this->getFilesystem());
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas($this->getSchemas($generatorConfig->getSection('paths')['schemaDir'], $generatorConfig->getSection('generator')['recursive']));
        $manager->setLoggerClosure(
            function ($message) use ($input, $output) {
                if ($input->getOption('verbose')) {
                    $output->writeln($message);
                }
            }
        );
        $manager->setWorkingDirectory($generatorConfig->getSection('paths')['phpDir']);
        $manager->build();
    }
    
    /**
    /**
     * Build SQL
     *
     * @param InputInterface  $input  Command Input
     * @param OutputInterface $output OutputInterface
     *
     * @return null
     */
    protected function sqlBuild(InputInterface $input, OutputInterface $output)
    {
        $configOptions = [];

        foreach ($input->getOptions() as $key => $option) {
            if (null !== $option) {
                switch ($key) {
                    case 'schema-dir':
                        $configOptions['propel']['paths']['schemaDir'] = $option;
                        break;
                    case 'sql-dir':
                        $configOptions['propel']['paths']['sqlDir'] = $option;
                        break;
                    case 'schema-name';
                        $configOptions['propel']['generator']['schema']['basename'] = $option;
                    break;
                    case 'table-prefix':
                        $configOptions['propel']['generator']['tablePrefix'] = $option;
                        break;
                    case 'mysql-engine';
                        $configOptions['propel']['database']['adapters']['mysql']['tableType'] = $option;
                    break;
                    case 'composer-dir':
                        $configOptions['propel']['paths']['composerDir'] = $option;
                        break;
                }
            }
        }

        $generatorConfig = $this->getGeneratorConfig($configOptions, $input);

        $this->createDirectory($generatorConfig->getSection('paths')['sqlDir']);

        $manager = new SqlManager();

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
        $manager->setOverwriteSqlMap($input->getOption('overwrite'));
        $manager->setConnections($connections);

        $manager->setValidate($input->getOption('validate'));
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas($this->getSchemas($generatorConfig->getSection('paths')['schemaDir'], $generatorConfig->getSection('generator')['recursive']));
        $manager->setLoggerClosure(
            function ($message) use ($input, $output) {
                if ($input->getOption('verbose')) {
                    $output->writeln($message);
                }
            }
        );
        $manager->setWorkingDirectory($generatorConfig->getSection('paths')['sqlDir']);

        if (!$manager->isOverwriteSqlMap() && $manager->existSqlMap()) {
            $output->writeln(
                "<info>sqldb.map won't be saved because it already exists. Remove it to generate a new map. Use --overwrite to force a overwrite.</info>"
            );
        }

        $manager->buildSql();
    }
    
    /**
     * Execute Migrations
     *
     * @param InputInterface  $input  Command Input
     * @param OutputInterface $output OutputInterface
     *
     * @return null
     */
    protected function executeMigrations(InputInterface $input, OutputInterface $output)
    {
        $configOptions = [];

        if ($this->hasInputOption('migration-dir', $input)) {
            $configOptions['propel']['paths']['migrationDir'] = $input->getOption('migration-dir');
        }
        if ($this->hasInputOption('migration-table', $input)) {
            $configOptions['propel']['migrations']['tableName'] = $input->getOption('migration-table');
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
        $manager->setMigrationTable($generatorConfig->getSection('migrations')['tableName']);
        $manager->setWorkingDirectory($generatorConfig->getSection('paths')['migrationDir']);

        if (!$manager->getFirstUpMigrationTimestamp()) {
            $output->writeln('All migrations were already executed - nothing to migrate.');

            return false;
        }

        $timestamps = $manager->getValidMigrationTimestamps();
        if (count($timestamps) > 1) {
            $output->writeln(sprintf('%d migrations to execute', count($timestamps)));
        }

        foreach ($timestamps as $timestamp) {
            $output->writeln(
                sprintf(
                    'Executing migration %s up',
                    $manager->getMigrationClassName($timestamp)
                )
            );
            $migration = $manager->getMigrationObject($timestamp);
            if (property_exists($migration, 'comment') && $migration->comment) {
                $output->writeln(sprintf('<info>%s</info>', $migration->comment));
            }

            if (false === $migration->preUp($manager)) {
                if ($input->getOption('force')) {
                    $output->writeln('<error>preUp() returned false. Continue migration.</error>');
                } else {
                    $output->writeln('<error>preUp() returned false. Aborting migration.</error>');

                    return false;
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

            // migrations for datasources have passed - update the timestamp
            // for all datasources
            foreach ($manager->getConnections() as $datasource => $connection) {
                $manager->updateLatestMigrationTimestamp($datasource, $timestamp);
                if ($input->getOption('verbose')) {
                    $output->writeln(
                        sprintf(
                            'Updated latest migration date to %d for datasource "%s"',
                            $timestamp,
                            $datasource
                        )
                    );
                }
            }

            if (!$input->getOption('fake')) {
                $migration->postUp($manager);
            }
        }

        $output->writeln('Migration complete. No further migration to execute.');
    }
}
