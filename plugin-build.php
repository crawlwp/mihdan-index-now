<?php
$dir               = @$argv[1] ?: dirname(__FILE__) . '/build';
$plugin_git_name   = 'mihdan-index-now';
$plugin_git_folder = $dir . '/' . $plugin_git_name;

function deleteDir($path)
{
    if (PHP_OS === 'Windows') {
        exec("rd /s /q {$path}");
    } else {
        exec("rm -rf {$path}");
    }
}

chdir($dir);

if (file_exists($plugin_git_folder)) {
    deleteDir($plugin_git_folder);
}

deleteDir($plugin_git_folder . '.zip');

exec("git clone https://github.com/crawlwp/mihdan-index-now.git $plugin_git_name");
$chdir = chdir($plugin_git_folder);
if ( ! $chdir) exit;

exec('composer install --no-dev --prefer-dist --no-progress --no-suggest');
exec('composer prefix-dependencies');

deleteDir('.git');
deleteDir('.github');
deleteDir('.wordpress-org');
deleteDir('php-scoper');
deleteDir('codekit');

deleteDir('vendor');

foreach (
    array(
        '.gitignore',
        '.distignore',
        '.editorconfig',
        'scoper.inc.php',
        'composer.json',
        'README.md',
        'plugin-build.php',
        'composer.lock',
        'mihdan-index-now.zip',
    ) as $file
) {
    @unlink($file);
}

// move up directory
chdir($dir);
exec("7z a mihdan-index-now.zip mihdan-index-now/");
deleteDir($plugin_git_name);

