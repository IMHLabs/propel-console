<?php
/**
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

use Propel\Generator\Manager\SqlManager as baseClass;

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
class SqlManager extends baseClass
{
    /**
     * Get Sql String
     *
     * @return string
     */
    public function getSql()
    {
        $sql = [];
        foreach ($this->getDatabases() as $datasource => $database) {
            $platform                  = $database->getPlatform();
            $sql[$database->getName()] = $platform->getAddTablesDDL($database);
        }
        return $sql;
    }
}
