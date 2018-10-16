<?php
/**
 * Propel Extended Console Commands
 *
 * Library to provide extended propel console commands
 *
 * PHP Version: 5
 *
 * @category  InMotion
 * @package   PropelConsole\Generator\Manager
 * @author    IMH Development <development@inmotionhosting.com>
 * @copyright 2018 Copyright (c) InMotion Hosting
 * @license   https://inmotionhosting.com proprietary
 * @link      https://inmotionhosting.com
 */
namespace PropelConsole\Generator\Manager;

use Propel\Generator\Manager\MigrationManager;

/**
 * Propel Extended Console Commands
 *
 * @category  InMotion
 * @package   PropelConsole\Generator\Manager
 * @author    IMH Development <development@inmotionhosting.com>
 * @copyright 2018 Copyright (c) InMotion Hosting
 * @license   https://inmotionhosting.com proprietary
 * @link      https://inmotionhosting.com
 */
class DataMigrationManager extends MigrationManager
{
    /**
     * Get Migration Timestamps
     *
     * @return array
     */
    public function getMigrationTimestamps()
    {
        $path = $this->getWorkingDirectory();
        $migrationTimestamps = [];

        if (is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $file) {
                $fileFound = preg_match(
                    '/^PropelDataMigration_(\d+).*\.php$/',
                    $file,
                    $matches
                );
                if ($fileFound) {
                    $migrationTimestamps[] = (integer) $matches[1];
                }
            }
        }

        return $migrationTimestamps;
    }

    /**
     * Get Migration Classname
     *
     * @param int    $timestamp Timestamp
     * @param string $suffix    Suffix
     *
     * @return string
     */
    public function getMigrationClassName($timestamp, $suffix = "")
    {
        $className = sprintf('PropelDataMigration_%d', $timestamp);
        if ($suffix === "") {
            $suffix = $this->findMigrationClassNameSuffix($timestamp);
        }
        if ($suffix !== "") {
            $className .= '_' . $suffix;
        }
        return $className;
    }

    /**
     * Get Migration Classname Suffix
     *
     * @param int $timestamp Timestamp
     *
     * @return string
     */
    public function findMigrationClassNameSuffix($timestamp)
    {
        $suffix = "";
        $path = $this->getWorkingDirectory();
        if (is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $file) {
                $fileFound = preg_match(
                    '/^PropelDataMigration_'.$timestamp.'(_)?(.*)\.php$/',
                    $file,
                    $matches
                );
                if ($fileFound) {
                    $suffix = (string) $matches[2];
                }
            }
        }
        return $suffix;
    }

    /**
     * Get Migration Class Body
     *
     * @param string $migrationsUp   Migration Up Sql
     * @param string $migrationsDown Migration Down Sql
     * @param string $timestamp      Migration Timestamp
     * @param string $comment        Migration Comment
     * @param string $suffix         Migration Suffix
     *
     * @return string
     */
    public function getMigrationClassBody(
        $migrationsUp,
        $migrationsDown,
        $timestamp,
        $comment = "",
        $suffix = ""
    ) {
        $timeInWords = date('Y-m-d H:i:s', $timestamp);
        $migrationAuthor = ($author = $this->getUser()) ? 'by ' . $author : '';
        $migrationClassName = $this->getMigrationClassName($timestamp, $suffix);
        $migrationUpString = var_export($migrationsUp, true);
        $migrationDownString = var_export($migrationsDown, true);
        $commentString = var_export($comment, true);
        $migrationClassBody = <<<EOP
<?php

use PropelConsole\Generator\Manager\DataMigrationManager as MigrationManager;

/**
 * Data object containing the SQL and PHP code to apply a data migration to 
 * the database up to version $timestamp.
 * Generated on $timeInWords $migrationAuthor
 */
class $migrationClassName
{
    public \$comment = $commentString;

    public function preUp(MigrationManager \$manager)
    {
        // add the pre-migration code here
    }

    public function postUp(MigrationManager \$manager)
    {
        // add the post-migration code here
    }

    public function preDown(MigrationManager \$manager)
    {
        // add the pre-migration code here
    }

    public function postDown(MigrationManager \$manager)
    {
        // add the post-migration code here
    }

    /**
     * Get the SQL statements for the Up migration
     *
     * @return array list of the SQL strings to execute for the Up migration
     *               the keys being the datasources
     */
    public function getUpSQL()
    {
        return $migrationUpString;
    }

    /**
     * Get the SQL statements for the Down migration
     *
     * @return array list of the SQL strings to execute for the Down migration
     *               the keys being the datasources
     */
    public function getDownSQL()
    {
        return $migrationDownString;
    }

}
EOP;

        return $migrationClassBody;
    }
}
