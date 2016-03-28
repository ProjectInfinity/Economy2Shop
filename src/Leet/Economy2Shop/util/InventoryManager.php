<?php

namespace Leet\Economy2Shop\util;

use Leet\Economy2Shop\Economy2Shop;
use pocketmine\item\Item;
use pocketmine\utils\Config;

class InventoryManager {

    private $plugin, $data;

    public function __construct(Economy2Shop $plugin) {
        $this->plugin = $plugin;
        $this->data = new Config($plugin->getDataFolder().'inventory.yml', Config::YAML);
    }

    /**
     * Gets the inventory by the specified player.
     *
     * @param $player
     * @return array|null
     */
    public function getInventory($player) {
        $player = strtolower($player);
        return $this->data->getNested('inventory.'.$player);
    }

    /**
     * Returns true if the player has the item
     * and the specified quantity.
     *
     * @param $player
     * @param Item $item
     * @param $quantity
     * @return bool
     */
    public function has($player, Item $item, $quantity) {

        foreach($this->data->getNested('inventory.'.strtolower($player), []) as $i => $q) {
            $i = explode('-', $i);
            if(count($i) < 2) return false;
            if((int) $i[0] !== $item->getId() or (int) $i[1] !== $item->getDamage()) continue;
            if($q >= $quantity) return true;
        }

        return false;

    }

    /**
     * Returns true if the items were removed,
     * otherwise false. Only call this after
     * having called $this->has();
     *
     * @param $player
     * @param Item $item
     * @return boolean
     */
    public function remove($player, Item $item) {

        $player = strtolower($player);
        $items = $this->data->getNested('inventory.'.$player);

        if($items === null) return false;

        foreach($items as $i => $q) {
            $i = explode('-', $i);
            if(count($i) < 2) return false;
            if((int) $i[0] !== $item->getId() or (int) $i[1] !== $item->getDamage()) continue;
            if($item->getCount() > $q) return false;
            if($item->getCount() === $q)
                unset($items[$item->getId().'-'.$item->getDamage()]);
            else
                $items[$item->getId().'-'.$item->getDamage()] = $q - $item->getCount();
        }

        $this->data->setNested('inventory.'.$player, $items);

        $this->data->save();

        return true;

    }

    /**
     * Returns true if the item has been added,
     * otherwise returns false.
     *
     * @param $player
     * @param Item $item
     * @return bool
     */
    public function add($player, Item $item) {
        $player = strtolower($player);

        $items = $this->data->getNested('inventory.'.$player, []);

        $key = $item->getId().'-'.$item->getDamage();
        $exists = isset($items[$key]);

        $prev = null;
        if($exists) $prev = (int) $items[$key];

        $items[$key] = $exists ? (int) $items[$key] + $item->getCount() : $item->getCount();

        $this->data->setNested('inventory.'.$player, $items);
        $this->data->save();

        if((int) $this->data->getNested('inventory.'.$player)[$key] === $item->getCount()) return true;
        if($exists and $prev !== null and (int) $this->data->getNested('inventory.'.$player)[$key] === ($prev + $item->getCount())) return true;

        return false;

    }

    /**
     * Saves the data to disk.
     */
    public function save() {
        $this->data->save();
    }

}