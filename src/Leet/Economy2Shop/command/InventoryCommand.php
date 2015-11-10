<?php

namespace Leet\Economy2Shop\command;

use Leet\Economy2Shop\Economy2Shop;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class InventoryCommand implements CommandExecutor {

    private $plugin, $inventory;

    public function __construct(Economy2Shop $plugin) {
        $this->plugin = $plugin;
        $this->inventory = $plugin->getInventoryManager();
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {

        if(!$sender->hasPermission('economy2shop.command.inventory')) {
            $sender->sendMessage(TextFormat::RED.'You do not have permission to do that.');
            return true;
        }

        if(count($args) < 1) {
            $sender->sendMessage(TextFormat::RED.'You have to specified a valid action.');
            $sender->sendMessage(TextFormat::YELLOW.'Valid actions: add, remove, list');
            return true;
        }


        switch(strtoupper($args[0])) {

            case 'LIST':
                $inventory = $this->inventory->getInventory($sender->getName());
                if($inventory === null) {
                    $sender->sendMessage(TextFormat::YELLOW.'You do not have any items in your inventory.');
                    return true;
                }
                arsort($inventory);
                $i = 1;
                foreach($inventory as $item => $amount) {
                    $itemData = explode('-', $item);
                    $realItem = Item::get((int) $itemData[0], (int) $itemData[1]);
                    $sender->sendMessage(TextFormat::YELLOW.$i.'. '.TextFormat::AQUA.
                        $realItem->getName().TextFormat::GRAY.' ('.$itemData[0].':'.$itemData[1].') '.
                        TextFormat::AQUA.'x'.$amount);
                    $i++;
                }
                break;

            case 'ADD':

                if(!($sender instanceof Player)) {
                    $sender->sendMessage(TextFormat::RED.'Sorry Mac, only players can do that.');
                    return true;
                }
                $item = $sender->getInventory()->getItemInHand();
                if($item->getName() === 'Air') {
                    $sender->sendMessage(TextFormat::RED.'You need to hold an item in your hand that you wish to deposit.'.
                        TextFormat::YELLOW.' You can add a number like \'/inventory add 5\' to add 5 of that item.');
                    return true;
                }

                # Check if the player specified an amount.
                if(count($args) > 1) {
                    if(!is_numeric($args[1])) {
                        $sender->sendMessage(TextFormat::RED.'When specifying an amount it has to be a number!');
                        return true;
                    }
                    $amount = (int) $args[1];
                    $item->setCount($amount);
                } else {
                    $amount = $item->getCount();
                }

                if($amount <= 0) {
                    $sender->sendMessage(TextFormat::RED.'Invalid quantity');
                    return true;
                }

                # Check if the player has enough of that item.
                if(!$sender->getInventory()->contains($item)) {
                    $sender->sendMessage(TextFormat::RED.'You do not have the specified amount of that item.');
                    return true;
                }

                if($this->inventory->add($sender->getName(), $item)) {
                    $sender->sendMessage(TextFormat::GREEN.'You successfully deposited '.TextFormat::AQUA.$item->getName().
                        ' x'.$item->getCount());
                    $sender->getInventory()->removeItem($item);
                } else {
                    $sender->sendMessage(TextFormat::RED.'Failed to add the item into your inventory.');
                }

                break;

            case 'REMOVE':

                if(!($sender instanceof Player)) {
                    $sender->sendMessage(TextFormat::RED.'Sorry Mac, only players can do that.');
                    return true;
                }

                $argCount = count($args);
                if($argCount === 1) {
                    $sender->sendMessage(TextFormat::RED.'You need to specify a item id, meta and an amount.');
                    return true;
                }

                /** @var Item|null $item */
                $item = null;

                # /inventory remove [ItemName/ID/ID:Meta]
                if($argCount === 2) {

                    # Check if ID:Meta is specified.
                    $data = explode(':', $args[1]);
                    # The line contained ':'.
                    if(count($data) > 1) {

                        # Ensure that both arguments are numeric.
                        if(!is_numeric($data[0]) or !is_numeric($data[1])) {
                            $sender->sendMessage(TextFormat::RED.'You have to specify a valid item ID and meta. E.g. 1:0 for Stone.');
                            return true;
                        }

                        # Check if inventory contains item so we can get the quantity.
                        $items = $this->inventory->getInventory($sender->getName());
                        if(!isset($items[$data[0].'-'.$data[1]])) {
                            $sender->sendMessage(TextFormat::RED.'Your inventory does not contain the specified item.');
                            return true;
                        }

                        $item = Item::get((int) $data[0], (int) $data[1], (int) $items[$data[0].'-'.$data[1]]);

                    } elseif(count($data) === 1) {
                        # The line did not contain ':'.

                        # The argument may be a item ID without meta.
                        if(is_numeric($args[1])) {

                            # Check if inventory contains item so we can get the quantity.
                            $items = $this->inventory->getInventory($sender->getName());
                            if(!isset($items[$args[1].'-'.'0'])) {
                                $sender->sendMessage(TextFormat::RED.'Your inventory does not contain the specified item.');
                                return true;
                            }

                            $item = Item::get((int) $data[0], 0, (int) $items[$args[1].'-'.'0']);

                        } else {
                            # The argument may be a one-word item.
                            Item::fromString($args[1]); # TODO: This requires further testing.
                        }

                    }

                }

                # /inventory remove [ItemName/ID/ID:META] [AMOUNT] OR /inventory remove [Long Item Name]
                if($argCount > 2) {

                    # Check if ID:Meta is specified.
                    $data = explode(':', $args[1]);
                    # The line contained ':'.
                    if(count($data) > 1) {

                        # Ensure that both arguments are numeric.
                        if(!is_numeric($data[0]) or !is_numeric($data[1])) {
                            $sender->sendMessage(TextFormat::RED.'You have to specify a valid item ID and meta. E.g. 1:0 for Stone.');
                            return true;
                        }

                        # Ensure that the specified quantity is only numbers.
                        if(!is_numeric($args[2])) {
                            $sender->sendMessage(TextFormat::RED.'The specified quantity is invalid. Quantity can only be whole numbers.');
                            return true;
                        }

                        # Check if inventory contains item so we can get the quantity.
                        $items = $this->inventory->getInventory($sender->getName());
                        if(!isset($items[$data[0].'-'.$data[1]])) {
                            $sender->sendMessage(TextFormat::RED.'Your inventory does not contain the specified item.');
                            return true;
                        }

                        $item = Item::get((int) $data[0], (int) $data[1], (int) $args[2]);

                    } elseif(count($data) === 1) {
                        # The line did not contain ':'.

                        # The argument may be a item ID without meta.
                        if(is_numeric($args[1])) {

                            # Ensure that the specified quantity is only numbers.
                            if(!is_numeric($args[2])) {
                                $sender->sendMessage(TextFormat::RED.'The specified quantity is invalid. Quantity can only be whole numbers.');
                                return true;
                            }

                            # Check if inventory contains item so we can get the quantity.
                            $items = $this->inventory->getInventory($sender->getName());
                            if(!isset($items[$args[1].'-'.'0'])) {
                                $sender->sendMessage(TextFormat::RED.'Your inventory does not contain the specified item.');
                                return true;
                            }

                            $item = Item::get((int) $data[0], 0, (int) $args[2]);

                        } else {
                            # The argument may be a one-word item.
                            $args[0] = null;
                            Item::fromString(implode(' ', $args)); # TODO: This requires further testing.
                        }

                    }

                }

                # Check if the item is valid.
                if($item === null or ($item !== null AND $item->getName() === 'Air')) {
                    $sender->sendMessage(TextFormat::RED.'Invalid item specified.');
                    return true;
                }

                # Check if amount is not invalid.
                if($item->getCount() < 1) {
                    $sender->sendMessage(TextFormat::RED.'The specified amount has to be 1 or above.');
                    return true;
                }

                # Check if the player actually has the items he/she claims to have.
                if(!$this->inventory->has($sender->getName(), $item, $item->getCount())) {
                    $sender->sendMessage(TextFormat::RED.'You do not have the specified amount of that item.');
                    return true;
                }

                # Check that the player can actually store the items in their inventory. Otherwise cancel.
                if(!$sender->getInventory()->canAddItem($item)) {
                    $sender->sendMessage(TextFormat::RED.'You do not have enough space in your inventory to do that.');
                    return true;
                }

                $this->inventory->remove($sender->getName(), $item);
                $sender->getInventory()->addItem($item);

                $sender->sendMessage(TextFormat::GREEN.'You withdrew '.TextFormat::AQUA.$item->getName().' x'.$item->getCount());

                break;

            default:
                $sender->sendMessage(TextFormat::RED.'You have to specified a valid action.');
                $sender->sendMessage(TextFormat::YELLOW.'Valid actions: add, remove, list');
        }

        return true;

    }
}