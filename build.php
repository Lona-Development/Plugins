<?php

// Delete all PHAR files in current directory
foreach (glob('build/*.phar') as $file) {
    unlink($file);
}

if(!is_dir('build')) {
    mkdir('build');
}

// Loop through all directories and create PHAR files
$folders = scandir('.');
foreach ($folders as $folder) {
    if (is_dir($folder) && $folder != '.' && $folder != '..') {
        if(file_exists($folder.'/plugin.json') == false) {
            print 'Skipping '.$folder.' (no plugin.json)'.PHP_EOL;
            continue;
        }

        $config = json_decode(file_get_contents($folder.'/plugin.json'), true);

        print 'Building '.$config['name'].'-'.$config['version'].'.phar'.PHP_EOL;

        $phar = new Phar("build/".$config['name'].'-'.$config['version'].'.phar', 0, $folder.'/build/'.$config['name'].'-'.$config['version'].'.phar');
        $phar->buildFromDirectory($folder.'/');
        $phar->setDefaultStub($config['main']['namespace'].'/'.$config['main']['class'].'.php', $config['main']['namespace'].'/'.$config['main']['class'].'.php');
        $phar->setAlias($config['name'].'-'.$config['version'].'.phar');
        $phar->stopBuffering();
    }
}
