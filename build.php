<?php

// Delete all PHAR files in current directory
foreach (glob('*.phar') as $file) {
    unlink($file);
}

// Loop through all directories and create PHAR files
// // Build the PHAR
/*                                        $phar = new \Phar("plugins/".$conf['name']."-".$conf['version'].".phar", 0,
                                            "plugins/".$conf['name']."-".$conf['version'].".phar");
                                        $phar->buildFromDirectory("plugins/".$r."/");
                                        $phar->setDefaultStub($conf['main']['namespace'].'/'.$conf['main']['class'].'.php',
                                            $conf['main']['namespace'].'/'.$conf['main']['class'].'.php');
                                        $phar->setAlias($conf['name']."-".$conf['version'].".phar");
                                        $phar->stopBuffering();*/
$folders = scandir('.');
foreach ($folders as $folder) {
    if (is_dir($folder) && $folder != '.' && $folder != '..') {
        if(file_exists($folder.'/plugin.json') == false) {
            print 'Skipping '.$folder.' (no plugin.json)'.PHP_EOL;
            continue;
        }

        $config = json_decode(file_get_contents($folder.'/plugin.json'), true);

        print 'Building '.$config['name'].'-'.$config['version'].'.phar'.PHP_EOL;

        $phar = new Phar($config['name'].'-'.$config['version'].'.phar', 0, $folder.'/'.$config['name'].'-'.$config['version'].'.phar');
        $phar->buildFromDirectory($folder.'/');
        $phar->setDefaultStub($config['main']['namespace'].'/'.$config['main']['class'].'.php', $config['main']['namespace'].'/'.$config['main']['class'].'.php');
        $phar->setAlias($config['name'].'-'.$config['version'].'.phar');
        $phar->stopBuffering();
    }
}
