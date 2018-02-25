<?php

namespace ProjectInfinity\Economy2Shop;

use ProjectInfinity\Economy2Shop\command\InventoryCommand;
use ProjectInfinity\Economy2Shop\command\ItemInfoCommand;
use ProjectInfinity\Economy2Shop\listener\ShopListener;
use ProjectInfinity\Economy2Shop\util\InventoryManager;
use pocketmine\plugin\PluginBase;

class Economy2Shop extends PluginBase {

    private static $plugin;

    private $inventoryManager;

    public function onEnable() {

        self::$plugin = $this;

        $this->saveDefaultConfig();
        $this->reloadConfig();

        $this->inventoryManager = new InventoryManager($this);

        # Check if Economy2 is loaded and disable if it isn't.
        if($this->getServer()->getPluginManager()->getPlugin('Economy2') === null) {
            $this->getLogger()->critical('Economy2 is not loaded, therefore Economy2Shop cannot function correctly!');
            $this->getPluginLoader()->disablePlugin($this);
            return;
        }

        # Register commands.
        $this->getCommand('iteminfo')->setExecutor(new ItemInfoCommand($this));
        $this->getCommand('inventory')->setExecutor(new InventoryCommand($this));

        # Register event listeners.
        $this->getServer()->getPluginManager()->registerEvents(new ShopListener($this), $this);

    }

    public function onDisable() {

        # Cleanup in case of a reload.
        self::$plugin = null;

        unset($this->inventoryManager);

    }

    /** @return Economy2Shop */
    public static function getPlugin() {
        return self::$plugin;
    }

    /** @return InventoryManager */
    public function getInventoryManager() {
        return $this->inventoryManager;
    }

}