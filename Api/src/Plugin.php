<?php

namespace Plugin;

use LonaDB\Plugins\PluginBase;

class MainClass extends PluginBase
{
    public function onEnable(): void
    {
        // Hole den bestehenden Socket
        $lonaDB = $this->getLonaDB();
        $server = $lonaDB->getPluginManager()->getPlugin("Webinterface")->getServer();
        $socket = $server->getSocket();

        $address = '';
        $port = 0;
        if (socket_getsockname($socket, $address, $port)) {
            $this->getLogger()->info("Socket lauscht auf $address:$port");
        } else {
            $this->getLogger()->error("Konnte den Socket-Port nicht abrufen: " . socket_strerror(socket_last_error($socket)));
        }

        if ($socket === null) {
            $this->getLogger()->error("Socket konnte nicht aus dem Webinterface-Server geladen werden.");
            return;
        }

        $this->getLogger()->info("Socket erfolgreich geladen. Warte auf Anfragen...");

        // Haupt-Loop: Auf Verbindungen hören
        while (true) {
            // Verbindung akzeptieren
            $client = @socket_accept($socket);
            if ($client === false) {
                $this->getLogger()->error("Socket akzeptiert keine Verbindungen: " . socket_strerror(socket_last_error($socket)));
                continue;
            }

            $this->getLogger()->info("Client verbunden!");

            // Daten vom Client lesen
            $data = @socket_read($client, 1024);
            if ($data === false) {
                $this->getLogger()->error("Fehler beim Lesen vom Client: " . socket_strerror(socket_last_error($client)));
                socket_close($client);
                continue;
            }

            // Eingehende Daten ausgeben
            $this->getLogger()->info("Empfangene Daten vom Client:");
            var_dump($data);

            // Verbindung schließen
            socket_close($client);
        }
    }
}
