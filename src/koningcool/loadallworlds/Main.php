<?php
declare(strict_types=1);
namespace koningcool\loadallworlds;

use JsonException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use JackMD\UpdateNotifier\UpdateNotifier;
use function array_diff;
use function scandir;

class Main extends PluginBase
{
    private bool $debugMode = false;
    private array $configData = [];

    private function loadWorlds(string $excludelist, bool $showInfo) : void
    {
        $loadedLevelsBefore = count($this->getServer()->getWorldManager()->getWorlds());

        if ($this->debugMode === true) {
            $this->getLogger()->info(TextFormat::DARK_GREEN . "Worlds loaded before: " . $loadedLevelsBefore);
        }

        # Get appropriate exclude list (on-load, on-command, default = no list)
        $exclude = match ($excludelist) {
            "on-load" => $this->configData["on-startup"]["exclude"],
            "on-command" => $this->configData["on-command"]["exclude"],
            default => "",
        };

        if ($this->debugMode === true) {
            $this->getLogger()->info(TextFormat::DARK_GREEN . "Exclude mode: " . $excludelist);
            $this->getLogger()->info(TextFormat::DARK_GREEN . "Excluded worlds: " . $exclude);
        }

        # Load the levels
        foreach (array_diff(scandir($this->getServer()->getDataPath() . "worlds"), ["..", "."]) as $levelName) {
            # Only load level if not in exclude list, which can be empty
            $excludeArray = explode(",", $exclude);
            if (!in_array($levelName, $excludeArray)) {
                $this->getServer()->getWorldManager()->loadWorld($levelName);
            }
        }

        $loadedLevelsAfter = count($this->getServer()->getWorldManager()->getWorlds());

        if ($this->debugMode === true) {
            $this->getLogger()->info(TextFormat::DARK_GREEN . "Finished loading worlds.");
            $this->getLogger()->info(TextFormat::DARK_GREEN . "Worlds loaded after: " . $loadedLevelsAfter);
        }

        $this->getLogger()->info(TextFormat::DARK_GREEN . "Before: " . $loadedLevelsBefore);
        $this->getLogger()->info(TextFormat::DARK_GREEN . "After: " . $loadedLevelsAfter);
        $this->getLogger()->info(TextFormat::DARK_GREEN . "ShowInfo: " . $showInfo);
        $this->getLogger()->info(TextFormat::DARK_GREEN . "ShowInfo === true: " . ($showInfo === true));
        $this->getLogger()->info(TextFormat::DARK_GREEN . "Before>After: " . ($loadedLevelsAfter > $loadedLevelsBefore));
        $this->getLogger()->info(TextFormat::DARK_GREEN . "Before>After && showinfo: " . (($loadedLevelsAfter > $loadedLevelsBefore) && ($showInfo === true)));

        #if (($loadedLevelsAfter > $loadedLevelsBefore) && ($showInfo === true)) {
        if ($loadedLevelsAfter > $loadedLevelsBefore) {
            $this->getLogger()->info(TextFormat::DARK_GREEN . "One or more worlds were loaded.");
        } else {
            $this->getLogger()->info(TextFormat::DARK_RED . "No extra worlds loaded!");
        }
    }

    public function onLoad() : void
    {
        UpdateNotifier::checkUpdate($this->getDescription()->getName(), $this->getDescription()->getVersion());

        if ($this->debugMode === true) {
            $this->getLogger()->info(TextFormat::DARK_BLUE . "LoadAllWorlds Loaded!");
        }
    }

    public function onEnable() : void
    {
        if ($this->debugMode === true) {
            $this->getLogger()->info(TextFormat::DARK_GREEN . "LoadAllWorlds Enabled!");
        }
        $this->reloadConfig();
        $this->configData = $this->getConfig()->getAll();
        $this->migrateConfig();
    }

    public function onDisable() : void
    {
        try {
            $this->getConfig()->save();
        } catch (JsonException $e) {
            $this->getLogger()->info(TextFormat::DARK_RED . " " . $e->getMessage());
        }
        if ($this->debugMode === true) {
            $this->getLogger()->info(TextFormat::DARK_RED . "LoadAllWorlds Disabled!");
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool
    {
        switch ($command->getName()) {
            case "loadall":
                $this->loadWorlds("", true); # do not use any exclude list
                break;
            case "loadworlds":
                $this->loadWorlds("on-command", true); # use on-command exclude list
                break;
        }
        return true;
    }

    private function migrateConfig(): void
    {
        if (array_key_exists("config-version", $this->configData)) {
            if ($this->configData["config-version"] === 2) {
                if ($this->configData["on-startup"]["load-worlds"] === true) {
                    $this->loadWorlds("on-load", false); # use on-load exclude list
                }
                $this->debugMode = $this->getConfig()->get("debug");
            }
        } else {
            # Remove old config file
            unlink($this->getConfig()->getPath());
            # Write new config file (default)
            $this->saveDefaultConfig();
            $this->reloadConfig();
            # Set old value in new config and set default empty exclude list
            $oldvalue = array("load-worlds" => $this->configData["load-on-startup"], "exclude" => "");
            $this->getConfig()->set("on-startup", $oldvalue);
            # Save config
            try {
                $this->getConfig()->save();
            } catch (JsonException $e) {
                $this->getLogger()->info(TextFormat::DARK_RED . " " . $e->getMessage());
            }
            # Get the new config data in local storage
            $this->configData = $this->getConfig()->getAll();
        }
    }
}