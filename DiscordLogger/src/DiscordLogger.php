<?php

namespace Sunnyychi\LonaDB\Plugins;

use LonaDB\Plugins\PluginBase;

class DiscordLogger extends PluginBase
{
    private string $webhookUrl = "http://localhost/CHANGEME";

    public function onEnable(): void
    {
        if($this->GetLonaDB()->TableManager->GetTable("PluginConfiguration")) {
                if($this->GetLonaDB()->TableManager->GetTable("PluginConfiguration")->Get("DiscordLoggerWebhookURL", "root") != null)
                    $this->webhookUrl = $this->GetLonaDB()->TableManager->GetTable("PluginConfiguration")->Get("DiscordLoggerWebhookURL", "root");

            $this->GetLogger()->Load($this->GetName() . " on version " . $this->GetVersion() . " has been enabled");
            $this->sendDiscordMessage("Plugin `" . $this->GetName() . "` (v" . $this->GetVersion() . ") has been enabled.");
        }
    }

    private function sendDiscordMessage(string $message): void
    {
        if($this->webhookUrl == null || $this->webhookUrl == "http://localhost/CHANGEME"){
            $this->GetLogger()->Error("Please change the Webhook URL in `DiscordLoggerWebhookURL` on table `PluginConfiguration`. If the table is missing, create it");
            return;
        }
        $postData = json_encode(['content' => $message]);
        $ch = curl_init($this->webhookUrl);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $this->GetLogger()->Error("Failed to send Discord message: " . curl_error($ch));
        } elseif ($httpCode >= 400) {
            $this->GetLogger()->Error("Failed to send Discord message: HTTP $httpCode - Response: $response");
        }

        curl_close($ch);
    }

    public function onTableCreate(string $executor, string $name): void
    {
        $message = "`$executor` created a table: `$name`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onTableDelete(string $executor, string $name): void
    {
        $message = "`$executor` deleted a table: `$name`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onValueSet(string $executor, string $table, string $name, string $value): void
    {
        $message = "`$executor` set value for `$name`: `$value` in `$table`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onValueRemove(string $executor, string $table, string $name): void
    {
        $message = "`$executor` removed value for `$name` in `$table`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onFunctionCreate(string $executor, string $name, string $content): void
    {
        $message = "`$executor` created a function: `$name`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onFunctionDelete(string $executor, string $name): void
    {
        $message = "`$executor` deleted a function: `$name`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onFunctionExecute(string $executor, string $name): void
    {
        $message = "`$executor` executed a function: `$name`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onUserCreate(string $executor, string $name): void
    {
        $message = "`$executor` created a user: `$name`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onUserDelete(string $executor, string $name): void
    {
        $message = "`$executor` deleted a user: `$name`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onEval(string $executor, string $content): void
    {
        $message = "`$executor` evaluated content: `$content`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onPermissionAdd(string $executor, string $user, string $permission): void
    {
        $message = "`$executor` added permission `$permission` to user `$user`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }

    public function onPermissionRemove(string $executor, string $user, string $permission): void
    {
        $message = "`$executor` removed permission `$permission` from user `$user`.";
        $this->sendDiscordMessage($message);
        $this->GetLogger()->Plugin($this->GetName(), $message);
    }
}
