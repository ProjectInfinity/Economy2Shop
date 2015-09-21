<?php

namespace Leet\Economy2Shop\command;

use Leet\Economy2Shop\Economy2Shop;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class ItemInfoCommand implements CommandExecutor {

    private $plugin;

    public function __construct(Economy2Shop $plugin) {
        $this->plugin = $plugin;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {

        if(!$sender->hasPermission('economy2shop.command.iteminfo')) {
            $sender->sendMessage(TextFormat::RED.'You do not have permission to use that command.');
            return true;
        }

        # Check if the user typed '/iteminfo'
        if(count($args) === 0 and ($sender instanceof Player)) {
            $hand = $sender->getInventory()->getItemInHand();
            # Return if hand is empty.
            if($hand->getName() === 'Air') {
                $sender->sendMessage(TextFormat::YELLOW.'Hold an item in your hand and type /iteminfo.');
                return true;
            }
            $sender->sendMessage(TextFormat::YELLOW.'Name: '.TextFormat::AQUA.$hand->getName());
            $sender->sendMessage(TextFormat::YELLOW.'Id & meta: '.TextFormat::AQUA.$hand->getId().':'.$hand->getDamage());
            $sender->sendMessage(TextFormat::YELLOW.'Count: '.TextFormat::AQUA.$hand->getCount());
            return true;
        }

        # Check if the user typed '/iteminfo item:meta'
        if(count($args) > 0 and count(explode(':', $args[0])) > 1) {

            $itemData = explode(':', $args[0]);

            if(!is_numeric($itemData[0]) or !is_numeric($itemData[1])) {
                $sender->sendMessage(TextFormat::RED.'Item ID and meta has to be a number.');
                return true;
            }

            $item = Item::get(intval($itemData[0], intval($itemData[1])));

            if($item->getName() === 'Air') {
                $sender->sendMessage(TextFormat::RED.'Either the item ID or meta does not match an item.');
                return true;
            }

            $sender->sendMessage(TextFormat::YELLOW.'Name: '.TextFormat::AQUA.$item->getName());
            $sender->sendMessage(TextFormat::YELLOW.'Id & meta: '.TextFormat::AQUA.$item->getId().':'.$item->getDamage());

            return true;

        }

        # Check if the user typed '/iteminfo Item Name'
        if(count($args) > 0) {

            $arg = implode(' ', $args);

            $item = Item::fromString($arg);

            if($item->getName() === 'Air') {
                $sender->sendMessage(TextFormat::RED.'Found no valid item with that name.');
                return true;
            }

            $sender->sendMessage(TextFormat::YELLOW.'Name: '.TextFormat::AQUA.$item->getName());
            $sender->sendMessage(TextFormat::YELLOW.'Id & meta: '.TextFormat::AQUA.$item->getId().':'.$item->getDamage());

            return true;

        }

        $sender->sendMessage('Type /iteminfo [item name] or /iteminfo [id:meta] for this command to do anything useful.');

        return true;

    }
}