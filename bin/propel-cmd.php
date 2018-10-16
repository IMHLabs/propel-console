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
if (!class_exists('\Symfony\Component\Console\Application')) {
    if (file_exists($file = __DIR__.'/../../../autoload.php')
     || file_exists($file = __DIR__.'/../autoload.php')) {
        require_once $file;
    } elseif (file_exists($file = __DIR__.'/../autoload.php.dist')) {
        require_once $file;
    }
}

use Symfony\Component\Finder\Finder;
use Propel\Runtime\Propel;
use Propel\Generator\Application;

$app = new Application('Propel', Propel::VERSION);

$finder = new Finder();

// Get Expanded Propel Command Classes
$finder = new Finder();
$finder->files()
    ->name('*.php')
    ->in(__DIR__.'/../src/Generator/Command')
    ->depth(0);
$ns = '\\PropelConsole\\Generator\\Command\\';

foreach ($finder as $file) {
    $r  = new \ReflectionClass($ns.$file->getBasename('.php'));
    if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command')
     && !$r->isAbstract()) {
        $app->add($r->newInstance());
    }
}

$app->run();
