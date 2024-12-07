<?php

namespace LonaDB\Plugins;

use LonaDB\Plugins\Server;
use LonaDB\Plugins\Request;
use LonaDB\Plugins\Response;

use LonaDB\Enums\Permission;
use LonaDB\Plugins\PluginBase;

class Webinterface extends PluginBase
{
    private int $port = 0;

    public function onEnable(): void
    {
        $this->checkConfiguration();

        if ($this->port != 0) {
            $this->startWebinterface();
        }
    }

    private function startWebinterface(): void {
        // Versuche, den Server in einem neuen Prozess zu starten
        $pid = pcntl_fork();
    
        if ($pid == -1) {
            // Fehler beim Forken
            die('Could not fork the process');
        } elseif ($pid) {
            // Elternprozess (kann weiterarbeiten)
            $this->getLogger()->plugin($this->getName(), "Starting Webinterface.");
            return;  // Elternprozess tut hier nichts weiter
        } else {
            // Kindprozess (startet den Server)
            $this->runServer();
            exit;  // Beende den Kindprozess nach dem Serverstart
        }
    }
    
    private function checkLogin(string $username, string $password): bool {
        $password = str_replace("\r\n", "", $password);
        $result = $this->getLonaDB()->getUserManager()->checkPassword($username, $password);
    
        return $result;
    }
    

    private function runServer(): void {
        $server = new Server($this, $this->port);

        $this->registerLoginManagement($server);
        $this->registerTableManagement($server);
        $this->registerVariableManagement($server);
        
        $server->get('/', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $response->render("views/index.view.lona", [
                "title" => "LonaDB - Home",
                "tables" => $this->getLonaDB()->getTableManager()->listTables($request->getSession()["username"])
            ]);
        });
    
        $server->get('/shutdown', function(Request $request, Response $response) use ($server) {
            $this->getLogger()->plugin($this->getName(), "Server shutting down... Webinterface v".$this->getVersion());
            $response->send("Server shutting down... LonaHTTP v0.1");
            $server->stop();
        });
    
        $server->listen();
    }

    private function registerVariableManagement(Server $server): void {
        $server->post('/set/variable', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return;
            }else return;

            $this->getLonaDB()->getTableManager()->getTable($request->getBody()['table'])->set(
                str_replace("\r\n", "", $request->getBody()["key"]),
                str_replace("\r\n", "", $request->getBody()["value"]), 
                $request->getSession()["username"]
            );
            $response->send("ok");
        });

        $server->post('/delete/variable', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return;
            }else return;

            $this->getLonaDB()->getTableManager()->getTable($request->getBody()['table'])->delete(
                str_replace("\r\n", "", $request->getBody()["key"]), 
                $request->getSession()["username"]
            );
            $response->send("ok");
        });
    }

    private function registerTableManagement(Server $server): void {
        $server->get('/tables/:name', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $table = $this->getLonaDB()->getTableManager()->getTable($request->parameter("name"));
            if(!$table) return $response->redirect("/");

            $data = $table->getData();
            $permissions = $table->getPermissions();
            $write = $table->checkPermission($request->getSession()["username"], Permission::WRITE);
            $owner = $table->getOwner();

            $response->render("views/table.view.lona", [
                "title" => "LonaDB - " . $request->parameter("name"),
                "tables" => $this->getLonaDB()->getTableManager()->listTables($request->getSession()["username"]),
                "data" => $data,
                "permissions" => $permissions,
                "write" => $write,
                "owner" => $owner
            ]);
        });

        $server->post('/create/table', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return;
            }else return;

            $this->getLonaDB()->getTableManager()->createTable(str_replace("\r\n", "", $request->getBody()["table"]), $request->getSession()["username"]);
            $response->send("ok");
        });

        $server->post('/delete/table', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return;
            }else return;

            $this->getLonaDB()->getTableManager()->deleteTable(str_replace("\r\n", "", $request->getBody()["table"]), $request->getSession()["username"]);
            $response->send("ok");
        });
    }

    private function registerLoginManagement(Server $server): void{
        $server->get('/login', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if($this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/");
            }

            $error = false;
            $message = "";
            
            if($request->getQueryParams()["error"] != null){
                $error = true;

                switch($request->getQueryParams()["error"]){
                    case "wrongLogin":
                        $message = "Wrong username or password";
                        break;
                    default:
                        $message = "An unknown error occurred.";
                        break;
                }
            }

            $response->render("views/login.view.lona", [
                "title" => "LonaDB - Sign in",
                "error" => $error,
                "message" => $message
            ]);
        });

        $server->post('/login', function(Request $request, Response $response) {
            if( $request->getBody()["username"] != null &&
                $request->getBody()["password"] != null){
                if($this->checkLogin($request->getBody()["username"], $request->getBody()["password"])){
                    $response->setSessionValue("username", $request->getBody()['username']);
                    $response->setSessionValue("password", str_replace("\r\n", "", $request->getBody()['password']));
                    return $response->redirect("/");
                }
            }

            $response->redirect("/login?error=wrongLogin");
        });

        $server->get('/logout', function(Request $request, Response $response) {
            $response->setSessionValue("username", null);
            $response->setSessionValue("password", null);
            $response->redirect("/login");
        });
    }
    

    private function checkConfiguration(): void {
        if ($this->getLonaDB()->getTableManager()->getTable("PluginConfiguration")) {
            if ($this->getLonaDB()->getTableManager()->getTable("PluginConfiguration")->get("WebinterfacePort", "root") != null) {
                $this->port = $this->getLonaDB()->getTableManager()->getTable("PluginConfiguration")->get("WebinterfacePort", "root");
            }else $this->getLogger()->error("Webinterface port has not been found.");

            $this->getLogger()->load($this->getName() . " on version " . $this->getVersion() . " has been enabled");
        } else {
            $this->getLogger()->error("Configuration table has not been found.");
        }
    }
}
