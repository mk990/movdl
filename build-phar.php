<?php

$pharFile = 'movdl.phar';

// Validate environment
if (ini_get('phar.readonly')) {
    die("Error: Run with 'php -d phar.readonly=0 build-phar.php'\n");
}

// Clean up
if (file_exists($pharFile)) {
    unlink($pharFile);
}

try {
    $phar = new Phar($pharFile);
    $phar->startBuffering();

    // Add files with better filtering
    $phar->buildFromDirectory(__DIR__, '/\.(php|json)$/');

    // Remove unwanted files
    $unwanted = ['build-phar.php', 'composer.lock'];
    foreach ($unwanted as $file) {
        if (isset($phar[$file])) {
            unset($phar[$file]);
        }
    }

    // Set stub with better error handling
    $stub = <<<'EOD'
#!/usr/bin/env php
<?php
Phar::mapPhar();
require 'phar://' . __FILE__ . '/index.php';
__HALT_COMPILER();
EOD;

    $phar->setStub($stub);
    $phar->stopBuffering();
    // Make executable on Unix
    if (PHP_OS_FAMILY !== 'Windows') {
        chmod($pharFile, 0755);
    }

    echo "PHAR file created: $pharFile\n";
} catch (Exception $e) {
    die('Build failed: ' . $e->getMessage() . "\n");
}
