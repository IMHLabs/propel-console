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
use PropelConsole\Generator\Manager\SqlManager;
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
class DumpSchemaCommand extends AbstractCommand
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
                'migration-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'The migration output directory'
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
            ->addOption(
                'composer-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory in which your composer.json resides',
                null
            )
            ->addOption(
                'suffix',
                null,
                InputOption::VALUE_OPTIONAL,
                'A suffix for the migration class',
                ''
            )
            ->addOption(
                'comment',
                "m",
                InputOption::VALUE_OPTIONAL,
                'A comment for the migration',
                ''
            )
            ->addOption(
                'editor',
                null,
                InputOption::VALUE_OPTIONAL,
                'The text editor to use to open diff files',
                null
            )
            ->setName(
                'migration:dump-schema'
            )
            ->setAliases(
                [
                    'dump-schema'
                ]
            )
            ->setDescription(
                'Dump database schema to a migration'
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
            if (!$this->hasInputOption('connection', $input)) {
                $input->setOption('config-dir', $migrationConfig['config_path']);
                $input->setOption('migration-dir', $migrationConfig['migration_path']);
                $input->setOption('output-dir', $migrationConfig['sql_path']);
                $input->setOption('schema-dir', $migrationConfig['schema_path']);
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
        }
        $configOptions = [];

        foreach ($input->getOptions() as $key => $option) {
            if (null !== $option) {
                switch ($key) {
                    case 'schema-dir':
                        $configOptions['propel']['paths']['schemaDir'] = $option;
                        break;
                    case 'output-dir':
                        $configOptions['propel']['paths']['sqlDir'] = $option;
                        break;
                    case 'migration-dir':
                        $configOptions['propel']['paths']['migrationDir'] = $option;
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

        if ($this->hasInputOption('connection', $input)) {
            foreach ($input->getOption('connection') as $conn) {
                $configOptions += $this->connectionToProperties($conn);
            }
        }

        $generatorConfig  = $this->getGeneratorConfig($configOptions, $input);

        $this->createDirectory($generatorConfig->getSection('paths')['sqlDir']);
        $this->createDirectory($generatorConfig->getSection('paths')['migrationDir']);

        $sqlManager       = new SqlManager();
        $migrationManager = new MigrationManager();

        $connections      = [];
        $optionConnections = $input->getOption('connection');
        if (!$optionConnections) {
            $connections = $generatorConfig->getBuildConnections();
        } else {
            foreach ($optionConnections as $connection) {
                list($name, $dsn, $infos) = $this->parseConnection($connection);
                $connections[$name] = array_merge(['dsn' => $dsn], $infos);
            }
        }
        $sqlManager->setOverwriteSqlMap($input->getOption('overwrite'));
        $sqlManager->setConnections($connections);
        $sqlManager->setValidate($input->getOption('validate'));

        $sqlManager->setGeneratorConfig($generatorConfig);
        $migrationManager->setGeneratorConfig($generatorConfig);

        $sqlManager->setSchemas(
            $this->getSchemas(
                $generatorConfig->getSection('paths')['schemaDir'],
                $generatorConfig->getSection('generator')['recursive']
            )
        );
        $migrationManager->setSchemas(
            $this->getSchemas(
                $generatorConfig->getSection('paths')['schemaDir'],
                $generatorConfig->getSection('generator')['recursive']
            )
        );
        $sqlManager->setLoggerClosure(
            function ($message) use ($input, $output) {
                if ($input->getOption('verbose')) {
                    $output->writeln($message);
                }
            }
        );
        $sqlManager->setWorkingDirectory($generatorConfig->getSection('paths')['sqlDir']);
        $sqlManager->buildSql();
        $sql = $sqlManager->getSql();
        
        $migrationsUp   = [];
        $migrationsDown = [];
        foreach ($migrationManager->getDatabases() as $appDatabase) {
            $name = $appDatabase->getName();
            $migrationsUp[$name]    = $sql[$name];
            $migrationsDown[$name]  = '';
        }
        $timestamp          = time();
        $migrationFileName  = $migrationManager->getMigrationFileName($timestamp, $input->getOption('suffix'));
        $migrationClassBody = $migrationManager->getMigrationClassBody($migrationsUp, $migrationsDown, $timestamp, $input->getOption('comment'), $input->getOption('suffix'));
        $file = $generatorConfig->getSection('paths')['migrationDir'] . DIRECTORY_SEPARATOR . $migrationFileName;
        file_put_contents($file, $migrationClassBody);
        $output->writeln(sprintf('"%s" file successfully created.', $file));
        
        if (null !== $editorCmd = $input->getOption('editor')) {
            $output->writeln(sprintf('Using "%s" as text editor', $editorCmd));
            shell_exec($editorCmd . ' ' . escapeshellarg($file));
        } else {
            $output->writeln('Now add SQL statements and data migration code as necessary.');
            $output->writeln('Once the migration class is valid, call the "migrate" task to execute it.');
        }
    }
}
