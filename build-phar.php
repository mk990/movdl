<?php

$pharFile = 'movdl.phar';
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
$stub = <<<EOD
#!/usr/bin/env php
<?php
Phar::mapPhar('movdl.phar');
require 'phar://movdl.phar/index.php';
__HALT_COMPILER();
EOD;
$phar->setStub($stub);

// Stop buffering
$phar->stopBuffering();

echo "PHAR file created: $pharFile\n";
