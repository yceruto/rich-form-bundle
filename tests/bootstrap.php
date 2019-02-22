<?php

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require __DIR__.'/../vendor/autoload.php';

$publicDir = __DIR__.'/Fixtures/App/public';
$_SERVER['PANTHER_WEB_SERVER_DIR'] = $publicDir;

// Test Setup: remove all the contents in the build/ directory
// (PHP doesn't allow to delete directories unless they are empty)
if (\is_dir($buildDir = __DIR__.'/../build')) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $fileinfo->isDir() ? \rmdir($fileinfo->getRealPath()) : \unlink($fileinfo->getRealPath());
    }
}

$application = new Application(new \AppKernel('test', true));
$application->setAutoExit(false);

// Create database
$input = new ArrayInput(['command' => 'doctrine:database:create']);
$application->run($input, new ConsoleOutput());

// Create database schema
$input = new ArrayInput(['command' => 'doctrine:schema:create']);
$application->run($input, new ConsoleOutput());

// Load fixtures of the AppTestBundle
$input = new ArrayInput(['command' => 'doctrine:fixtures:load', '--no-interaction' => true, '--append' => false]);
$application->run($input, new ConsoleOutput());

// Make a copy of the original SQLite database to use the same unmodified database in every test
\copy($buildDir.'/test.db', $buildDir.'/original_test.db');

// Install Assets
$input = new ArrayInput(['command' => 'assets:install', 'target' => $publicDir, '--symlink' => true]);
$application->run($input, new ConsoleOutput());

unset($input, $application);

echo "\n";
