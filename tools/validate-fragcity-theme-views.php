<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$themeViews = $root . '/themes/fragcity/views';

if (!is_dir($themeViews)) {
    fwrite(STDERR, "Theme views directory is missing.\n");
    exit(1);
}

$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$compiler = $app->make('blade.compiler');
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($themeViews, FilesystemIterator::SKIP_DOTS)
);

$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $source = file_get_contents($file->getPathname());
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $file->getPathname());
    }

    $compiled = $compiler->compileString($source);
    token_get_all($compiled, TOKEN_PARSE);
    $count++;
}

echo "Validated Blade compilation and PHP syntax for {$count} FragCity theme views.\n";
