<?php

$pharFile = 'movdl.phar';

require_once 'vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->load();

// Clean up
if (file_exists($pharFile)) {
    unlink($pharFile);
}
if (file_exists($pharFile . '.gz')) {
    unlink($pharFile . '.gz');
}

$phar = new Phar($pharFile);

// Start buffering
$phar->startBuffering();

// Add all files recursively
$phar->buildFromDirectory(__DIR__, '/\.(php|html|css|js)$/');

// Set the stub (entry point)
$defaultStub = $phar->createDefaultStub('index.php');
$phar->setStub($defaultStub);

// Stop buffering
$phar->stopBuffering();

echo "PHAR file created: $pharFile\n";
