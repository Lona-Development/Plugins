<?php

namespace Sunnyychi\LonaDB\Plugins;

use LonaDB\Plugins\PluginBase;

class AutomaticBackups extends PluginBase
{
    private string $interval = "none";

    public function onEnable(): void
    {
        if ($this->getLonaDB()->getTableManager()->getTable("PluginConfiguration")) {
            if ($this->getLonaDB()->getTableManager()->getTable("PluginConfiguration")->get("AutomaticBackupsInterval", "root") != null) {
                $this->interval = $this->getLonaDB()->getTableManager()->getTable("PluginConfiguration")->get("AutomaticBackupsInterval", "root");
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
        } else $this->backup();
    }

    private function setInterval(callable $callback, int $interval): void
    {
        while (true) {
            $callback();
            sleep($interval);
        }
    }

    private function backup(): void
    {
        if(!is_dir("./backups")) mkdir("./backups");
        $this->getLogger()->info("Creating backups...");
        $tables = $this->getLonaDB()->getTableManager()->listTables();
        forEach($tables as $table){
            $tableParts = explode(":", file_get_contents($this->getLonaDB()->getBasePath()."/data/tables/".$table.".lona"));
            $tableData = json_decode(openssl_decrypt($tableParts[0], "AES-256-CBC", $this->getLonaDB()->config["encryptionKey"], 0, base64_decode($tableParts[1])), true);

            $data["table"] = $tableData;
            if(explode(".", $this->getLonaDB()->getVersion())[0] >= "5"){
                $walParts = explode(":", file_get_contents($this->getLonaDB()->getBasePath()."/data/wal/".$table.".lona"));
                $data["wal"] = json_decode(openssl_decrypt($walParts[0], "AES-256-CBC", $this->getLonaDB()->config["encryptionKey"], 0, base64_decode($walParts[1])), true);
            }
            file_put_contents("./backups/".$table."_".date('Ymd-Hi', time()).".json", json_encode($data));
        }
     }
}
