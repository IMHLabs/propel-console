<?php
/**
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
class CreateDataMigrationCommand extends AbstractCommand
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
                'schema-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'The directory where the schema files are placed'
            )
            ->addOption(
                'output-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'The output directory where the migration files are located'
            )
            ->addOption(
                'connection',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Connection to use. Example: \'bookstore=mysql:host=127.0.0.1;dbname=test;user=root;password=foobar\' where "bookstore" is your propel database name (used in your schema.xml)',
                []
            )
            ->addOption(
                'editor',
                null,
                InputOption::VALUE_OPTIONAL,
                'The text editor to use to open diff files',
                null
            )
            ->addOption(
                'comment',
                "m",
                InputOption::VALUE_OPTIONAL,
                'A comment for the migration',
                ''
            )
            ->addOption(
                'suffix',
                null,
                InputOption::VALUE_OPTIONAL,
                'A suffix for the migration class',
                ''
            )
            ->setName(
                'migration:create-data-migration'
            )
            ->setAliases(
                [
                    'create-data-migration'
                ]
            )
            ->setDescription(
                'Create empty data migration'
            );
    }

    /**
     * Execute Command
     *
     * @param InputInterface  $input Command Input
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

        if ($this->hasInputOption('connection', $input)) {
            foreach ($input->getOption('connection') as $conn) {
                $configOptions += $this->connectionToProperties($conn);
            }
        }

        $configOptions['propel']['paths']['schemaDir']    = $input->getOption('schema-dir');
        $configOptions['propel']['paths']['migrationDir'] = $input->getOption('output-dir');

        $generatorConfig = $this->getGeneratorConfig($configOptions, $input);

        $this->createDirectory($generatorConfig->getSection('paths')['migrationDir']);

        $manager = new MigrationManager();
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas($this->getSchemas($generatorConfig->getSection('paths')['schemaDir'], $generatorConfig->getSection('generator')['recursive']));

        $migrationsUp   = [];
        $migrationsDown = [];
        foreach ($manager->getDatabases() as $appDatabase) {
            $name = $appDatabase->getName();
            $migrationsUp[$name]    = '';
            $migrationsDown[$name]  = '';
        }

        $timestamp          = time();
        $migrationFileName  = $manager->getMigrationFileName($timestamp, $input->getOption('suffix'));
        $migrationClassBody = $manager->getMigrationClassBody($migrationsUp, $migrationsDown, $timestamp, $input->getOption('comment'), $input->getOption('suffix'));

        $file = $generatorConfig->getSection('paths')['migrationDir'] . DIRECTORY_SEPARATOR . $migrationFileName;
        file_put_contents($file, $migrationClassBody);

        $output->writeln(sprintf('"%s" file successfully created.', $file));

        if (null !== $editorCmd = $input->getOption('editor')) {
            $output->writeln(sprintf('Using "%s" as text editor', $editorCmd));
            shell_exec($editorCmd . ' ' . escapeshellarg($file));
        } else {
            $output->writeln('Now add SQL statements and data migration code as necessary.');
            $output->writeln('Once the migration class is valid, call the "data-migrate" task to execute it.');
        }
    }
}
