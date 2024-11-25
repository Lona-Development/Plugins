<?php

namespace Sunnyychi\LonaDB\Plugins;

use LonaDB\Plugins\PluginBase;

class AutomaticBackups extends PluginBase
{
    private string $interval = "none";

    public function onEnable(): void
    {
        if ($this->getLonaDB()->tableManager->getTable("PluginConfiguration")) {
            if ($this->getLonaDB()->tableManager->getTable("PluginConfiguration")->get("AutomaticBackupsInterval", "root") != null) {
                $this->interval = $this->getLonaDB()->tableManager->getTable("PluginConfiguration")->get("AutomaticBackupsInterval", "root");
            }

            $this->getLogger()->load($this->getName() . " on version " . $this->getVersion() . " has been enabled");
        } else {
            $this->getLogger()->error("Configuration table has not been found.");
        }

        if ($this->interval != "none") {
            // Correctly declare and initialize the variable
            $intervalTime = 0;

            switch ($this->interval) {
                case "hourly":
                    $intervalTime = 3600;
                    break;
                case "daily":
                    $intervalTime = 86400;
                    break;
                case "weekly":
                    $intervalTime = 604800;
                    break;
            }

            if ($intervalTime > 0) {
                $this->setInterval(function () {
                    $this->backup();
                }, $intervalTime);
            }
        } else this->backup();
    }

    private function setInterval(callable $callback, int $interval): void
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->getLogger()->error("Failed to fork process for automatic backups.");
            return;
        }

        if ($pid === 0) {
            // Child process
            while (true) {
                $callback();
                sleep($interval);
            }
            exit(0); // Ensure child process terminates cleanly
        }
        // Parent process continues as normal
    }

    private function backup(): void
    {
        if(!is_dir("./backups")) mkdir("./backups");
        $this->getLogger()->info("Creating backups...");
        $tables = $this->getLonaDB()->tableManager->listTables();
        forEach($tables as $table){
            $data = $this->getLonaDB()->tableManager->getTable($table)->getData();
            file_put_contents("./backups/".$table."_".date('Ymd-Hi', time()).".json", json_encode($data));
        }
    }
}
