<?php

namespace LonaDB\Plugin\Webinterface;

use LonaDB\Plugin\Webinterface\Server;
use LonaDB\Plugin\Webinterface\Request;
use LonaDB\Plugin\Webinterface\Response;

use LonaDB\Enums\Permission;
use LonaDB\Enums\Event;
use LonaDB\Plugins\PluginBase;

class Main extends PluginBase
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
        $this->registerUserManagement($server);
        
        $server->get('/', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $createTables = $this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::TABLE_CREATE);

            $response->render("views/index.view.lona", [
                "title" => "LonaDB - Home",
                "createTables" => $createTables,
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
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $value = str_replace("\r\n", "", $request->getBody()["value"]);

            if(is_int($value)){
                $value = intval($value);
            }else if(is_float($value)){
                $value = floatval($value);
            }else if($value == "true" || $value == "false"){
                $value = $value == "true";
            }
            else if(json_decode($value) != null){
                $value = json_decode($value);
            }

            $this->getLonaDB()->getTableManager()->getTable($request->getBody()['table'])->set(
                str_replace("\r\n", "", $request->getBody()["key"]),
                $value, 
                $request->getSession()["username"]
            );

            $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::VALUE_SET, [
                "table" => $request->getBody()['table'],
                "name" => str_replace("\r\n", "", $request->getBody()["key"]),
                "value" => $value
            ]);

            $response->redirect("/tables/" . $request->getBody()['table']);
        });

        $server->post('/delete/variable', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $this->getLonaDB()->getTableManager()->getTable($request->getBody()['table'])->delete(
                str_replace("\r\n", "", $request->getBody()["key"]), 
                $request->getSession()["username"]
            );

            $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::VALUE_REMOVE, [
                "table" => $request->getBody()['table'],
                "name" => str_replace("\r\n", "", $request->getBody()["key"])
            ]);

            $response->redirect("/tables/" . $request->getBody()['table']);
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
            $read = $table->checkPermission($request->getSession()["username"], Permission::READ);
            $owner = $table->getOwner();

            if(!$read && !$write) return $response->redirect("/");
            if(!$read) $data = [];

            $createTables = $this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::TABLE_CREATE);

            $response->render("views/table.view.lona", [
                "title" => "LonaDB - " . $request->parameter("name"),
                "createTables" => $createTables,
                "tables" => $this->getLonaDB()->getTableManager()->listTables($request->getSession()["username"]),
                "table" => $request->parameter("name"),
                "data" => $data,
                "permissions" => $permissions,
                "write" => $write,
                "owner" => $owner,
                "username" => $request->getSession()["username"]
            ]);
        });

        $server->post('/user/table/add', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $owner = $this->getLonaDB()->getTableManager()->getTable($request->getBody()["table"])->getOwner();
            $user = $request->getBody()["username"];

            if($owner != $request->getSession()["username"]) return $response->send("{error: 'not_table_owner'}");

            if(!$this->getLonaDB()->getUserManager()->checkUser($user)) return $response->send("{error: 'user_not_found'}");

            if($request->getBody()["read"] == "true"){
                $this->getLonaDB()->getTableManager()->getTable($request->getBody()["table"])->addPermission($user, Permission::READ, $request->getSession()["username"]);
                $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::PERMISSION_ADD, [
                    "user" => $user,
                    "name" => $request->getBody()["table"]." - ".Permission::READ->value
                ]);
            }
            if($request->getBody()["write"] == "true"){
                $this->getLonaDB()->getTableManager()->getTable($request->getBody()["table"])->addPermission($user, Permission::WRITE, $request->getSession()["username"]);
                $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::PERMISSION_ADD, [
                    "user" => $user,
                    "name" => $request->getBody()["table"]." - ".Permission::WRITE->value
                ]);
            }
            
            $response->send("{success: true}");
        });

        $server->post('/user/table/update', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $owner = $this->getLonaDB()->getTableManager()->getTable($request->getBody()["table"])->getOwner();
            $user = $request->getBody()["username"];

            if($owner != $request->getSession()["username"]) return $response->send("{error: 'not_table_owner'}");

            if(!$this->getLonaDB()->getUserManager()->checkUser($user)) return $response->send("{error: 'user_not_found'}");

            $permission = Permission::findPermission($request->getBody()["permission"]);
            if($request->getBody()["value"] == true){
                $this->getLonaDB()->getTableManager()->getTable($request->getBody()["table"])->addPermission($user, $permission, $request->getSession()["username"]);
                $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::PERMISSION_ADD, [
                    "user" => $user,
                    "name" => $request->getBody()["permission"]
                ]);
            }else{
                $this->getLonaDB()->getTableManager()->getTable($request->getBody()["table"])->removePermission($user, $permission, $request->getSession()["username"]);
                $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::PERMISSION_REMOVE, [
                    "user" => $user,
                    "name" => $request->getBody()["table"]." - ".$request->getBody()["permission"]
                ]);
            }

            $response->send("{success: true}");
        });

        $server->post('/create/table', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $hasPermission = $this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::TABLE_CREATE);

            if(!$hasPermission) return $response->redirect("/");

            $this->getLonaDB()->getTableManager()->createTable(str_replace("\r\n", "", $request->getBody()["table"]), $request->getSession()["username"]);

            $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::TABLE_CREATE, [
                "name" => str_replace("\r\n", "", $request->getBody()["table"])
            ]);
            
            $response->redirect("/tables/" . str_replace("\r\n", "", $request->getBody()["table"]));
        });

        $server->post('/delete/table', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $hasPermission = $this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::TABLE_DELETE);

            if(!$hasPermission) return $response->redirect("/");

            $this->getLonaDB()->getTableManager()->deleteTable(str_replace("\r\n", "", $request->getBody()["table"]), $request->getSession()["username"]);

            $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::TABLE_DELETE, [
                "name" => str_replace("\r\n", "", $request->getBody()["table"])
            ]);
            $response->redirect("/");
        });
    }

    private function registerUserManagement(Server $server): void{
        $server->get('/users', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $hasPermission = ($this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::USER_CREATE) ||
                             $this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::USER_DELETE));

            if(!$hasPermission) return $response->redirect("/");

            $users = $this->getLonaDB()->getUserManager()->listUsers();

            $createTables = $this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::TABLE_CREATE);

            $response->render("views/users.view.lona", [
                "title" => "LonaDB - Users",
                "createTables" => $createTables,
                "tables" => $this->getLonaDB()->getTableManager()->listTables($request->getSession()["username"]),
                "users" => $users
            ]);
        });

        $server->post('/create/user', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $hasPermission = ($this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::USER_CREATE) ||
                $this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::USER_DELETE));

            if(!$hasPermission) return $response->redirect("/");

            $this->getLonaDB()->getUserManager()->createUser(str_replace("\r\n", "", $request->getBody()["user"]), str_replace("\r\n", "", $request->getBody()["password"]));

            $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::USER_CREATE, [
                "name" => str_replace("\r\n", "", $request->getBody()["user"])
            ]);
            $response->redirect("/users");
        });

        $server->post('/delete/user', function(Request $request, Response $response) {
            if( $request->getSession()["username"] != null &&
                $request->getSession()["password"] != null){
                if(!$this->checkLogin($request->getSession()["username"], $request->getSession()["password"]))
                    return $response->redirect("/login");
            }else return $response->redirect("/login");

            $hasPermission = ($this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::USER_CREATE) ||
                $this->getLonaDB()->getUserManager()->checkPermission($request->getSession()["username"], Permission::USER_DELETE));

            if(!$hasPermission) return $response->redirect("/");

            $this->getLonaDB()->getUserManager()->deleteUser(str_replace("\r\n", "", $request->getBody()["user"]));

            $this->getLonaDB()->getPluginManager()->runEvent($request->getSession()["username"], Event::USER_DELETE, [
                "name" => str_replace("\r\n", "", $request->getBody()["user"])
            ]);
            $response->redirect("/users");
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
