<?php

namespace Leet\Economy2Shop\listener;

use Leet\Economy2\Economy2;
use Leet\Economy2Shop\Economy2Shop;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat;

class ShopListener implements Listener {

    private $plugin, $money, $inventory;

    public function __construct(Economy2Shop $plugin) {
        $this->plugin = $plugin;
        $this->money = Economy2::getPlugin()->getMoneyHandler();
        $this->inventory = $plugin->getInventoryManager();
    }

    /**
     * Check if the sign created is a shop.
     *
     * @param SignChangeEvent $event
     */
    public function onSignChange(SignChangeEvent $event) {

        if($event->isCancelled()) return;

        $isShop = false;
        $isAdminShop = false;

        if(strtoupper($event->getLine(0)) === '[SHOP]') $isShop = true;
        if(strtoupper($event->getLine(0)) === '[ADMIN SHOP]' or strtoupper($event->getLine(0)) === '[ADMINSHOP]') {
            $isShop = true;
            $isAdminShop = true;
        }

        # Stop processing if the sign is not a shop.
        if(!$isShop) return;

        if($isAdminShop) {
            if(!$event->getPlayer()->hasPermission('economy2shop.admin.create')) {
                $event->getPlayer()->sendMessage(TextFormat::RED.'You do not have permission to create admin shops.');
                $this->breakSign($event->getBlock());
                return;
            }
        }

        if($isShop and !$isAdminShop) {
            if(!$event->getPlayer()->hasPermission('economy2shop.create')) {
                $event->getPlayer()->sendMessage(TextFormat::RED.'You do not have permission to create shops.');
                $this->breakSign($event->getBlock());
                $event->setCancelled(true);
                return;
            }
        }

        $line2 = explode(' ', $event->getLine(1));

        if(count($line2) !== 2) {
            $event->getPlayer()->sendMessage(TextFormat::RED.'Invalid format on second line. Format: Sell 15 or Buy 15');
            $this->breakSign($event->getBlock());
            return;
        }

        $type = strtoupper($line2[0]);
        $quantity = $line2[1];

        if($type !== 'BUY' AND $type !== 'SELL') {
            $event->getPlayer()->sendMessage(TextFormat::RED.'Invalid format on second line. Format: Sell 15 or Buy 15');
            $this->breakSign($event->getBlock());
            return;
        }

        # Ensure that the second line is the quantity of items.
        if(!is_numeric($quantity)) {
            $event->getPlayer()->sendMessage(TextFormat::RED.'The second line defines the quantity and needs to be a number.');
            $this->breakSign($event->getBlock());
            return;
        }

        $quantity = intval($quantity);

        if($quantity < 1) {
            $event->getPlayer()->sendMessage(TextFormat::RED.'The second line quantity has to be 1 or higher.');
            $this->breakSign($event->getBlock());
            return;
        }

        $price = $event->getLine(2);

        if(!is_numeric($price)) {
            $event->getPlayer()->sendMessage(TextFormat::RED.'The third line defines the price and has to be numeric. Example: 1.5');
            $this->breakSign($event->getBlock());
            return;
        }

        $price = floatval($price);

        if($price < 0.1) {
            $event->getPlayer()->sendMessage(TextFormat::RED.'The third line defines the price and has to be above 0.');
            $this->breakSign($event->getBlock());
            return;
        }

        $item = $event->getLine(3);

        if(is_numeric($item)) {
            $item = Item::get(intval($item), 0, $quantity);
        } elseif(count(explode(':', $item)) > 1) {
            $itemData = explode(':', $item);
            $item = Item::get(intval($itemData[0]), intval($itemData[2]), $quantity);
        } else {
            $event->getPlayer()->sendMessage(TextFormat::RED.'Invalid item.');
            return;
        }
        /*if(is_numeric($item)) {
            $item = Item::get(intval($item));
        } else {
            if(count(explode(':', $item)) > 1) {
                $itemData = explode(':', $item);
                $item = Item::get(intval($itemData[0]), intval($itemData[2]), $quantity);
            } else {
                $item = Item::fromString($item);
            }
        }*/

        if($item->getName() === 'Unknown' or $item->getName() === 'Air') {
            $event->getPlayer()->sendMessage(TextFormat::RED.'The fourth line defines the item and has to be a valid item ID.');
            $this->breakSign($event->getBlock());
            return;
        }

        $currency = $price > 1 ? $this->money->getPluralName() : $this->money->getSingularName();

        # Seems like all is good! Let's format that sign correctly.

        # Set name of seller.
        $event->setLine(0, $isAdminShop ? '[Admin Shop]' : $event->getPlayer()->getName());
        # Set SELL/BUY and quantity.
        $event->setLine(1, ($type === 'BUY' ? 'Buy' : 'Sell').' '.$quantity);
        # Set price and currency name.
        #var_dump(number_format($price, 2));
        $event->setLine(2, number_format($price, 2).' '.$currency);
        # Set item info.
        $event->setLine(3, $item->getName());

        $event->getPlayer()->sendMessage(TextFormat::GREEN.'Shop created!');

    }

    /**
     * Ensure that a shop is not destroyed by
     * a player who is not allowed to
     * destroy that shop.
     *
     * @param BlockBreakEvent $event
     */
    public function onBlockBreak(BlockBreakEvent $event) {

        # We only want to continue if the block in question is a Sign Post or Wall Sign.
        if($event->getBlock()->getId() !== 63 and $event->getBlock()->getId() !== 68) return;

        $tile = $event->getBlock()->getLevel()->getTile(new Vector3(
                $event->getBlock()->getX(),
                $event->getBlock()->getY(),
                $event->getBlock()->getZ())
        );

        # Double check that the tile is a Sign.
        if(!$tile instanceof Sign) {
            $this->plugin->getLogger()->error('Tile was not a instance of Sign at X: '.
                $event->getBlock()->getX().' Y: '.
                $event->getBlock()->getY().' Z: '.
                $event->getBlock()->getZ()
            );

            return;
        }

        # Stop processing if the sign is not a shop sign.
        if(!$this->isShopSign($tile)) return;

        # Check if the player can destroy admin shops.
        if(strtoupper($tile->getText()[0]) === '[Admin Shop]' and !$event->getPlayer()->hasPermission('economy2shop.admin.destroy')) {
            $event->getPlayer()->sendMessage(TextFormat::RED.'You do not have permission to destroy admin shops!');
            $event->setCancelled(true);
            return;
        }

        # Check if the player can destroy shops.
        if(!$event->getPlayer()->hasPermission('economy2shop.destroy')) {
            $event->getPlayer()->sendMessage(TextFormat::RED.'You do not have permission to destroy shops!');
            $event->setCancelled(true);
            return;
        }

        if(strtoupper($tile->getText()[0]) !== strtoupper($event->getPlayer()->getName()) and
            strtoupper($tile->getText()[0]) === '[Admin Shop]') {
            $event->getPlayer()->sendMessage(TextFormat::RED.'You can only destroy shops you own.');
            $event->setCancelled(true);
            return;
        }

    }

    /**
     * Check for inventory, perform transactions etc.
     *
     * @param PlayerInteractEvent $event
     */
    public function onPlayerInteract(PlayerInteractEvent $event) {

        # We only want to continue if the block in question is a Sign Post or Wall Sign.
        if($event->getBlock()->getId() !== 63 and $event->getBlock()->getId() !== 68) return;

        $tile = $event->getBlock()->getLevel()->getTile(new Vector3(
                $event->getBlock()->getX(),
                $event->getBlock()->getY(),
                $event->getBlock()->getZ())
        );

        # Double check that the tile is a Sign.
        if(!$tile instanceof Sign) {
            $this->plugin->getLogger()->error('Tile was not a instance of Sign at X: '.
                $event->getBlock()->getX().' Y: '.
                $event->getBlock()->getY().' Z: '.
                $event->getBlock()->getZ()
            );

            return;
        }

        # Stop processing if the sign is not a shop sign.
        if(!$this->isShopSign($tile)) return;

        $name = explode(' ', $tile->getText()[0]);
        $isPlayerShop = false;

        # Check if there's more than one word on the first line or not.
        if(count($name) === 1) {
            $isPlayerShop = true;
            $name = $name[0];
        }

        # Do not handle the event if the shop owner is clicking the sign.
        if($isPlayerShop and strtoupper($name) === strtoupper($event->getPlayer()->getName())) return;

        $item = $tile->getText()[3];

        $line2 = explode(' ', $tile->getText()[1]);

        $type = strtoupper($line2[0]);
        $quantity = $line2[1];

        $quantity = intval($quantity);

        if(is_numeric($item)) {
            $item = Item::get(intval($item), 0, $quantity);
        } elseif(count(explode(':', $item)) > 1) {
            $itemData = explode(':', $item);
            $item = Item::get(intval($itemData[0]), intval($itemData[2]), $quantity);
        } else {
            $event->getPlayer()->sendMessage(TextFormat::RED.'Invalid item.');
            return;
        }
        /*if(is_numeric($item)) {
            $item = Item::get(intval($item), 0, $quantity);
        } else {
            if(count(explode(':', $item)) > 1) {
                $itemData = explode(':', $item);
                $item = Item::get(intval($itemData[0]), intval($itemData[2]), $quantity);
            } else {
                $item = Item::fromString($item);
                $item->setCount($quantity);
            }
        } */

        $price = explode(' ', $tile->getText()[2])[0];

        $price = floatval($price);

        # Check if the player is buying.
        if($type === 'BUY') {

            # Check if player has enough stock.
            if($isPlayerShop and !$this->inventory->has($name, $item, $quantity)) {
                $event->getPlayer()->sendMessage(TextFormat::RED.$name.' is out of '.$item->getName());
                return;
            }

            # Check if buyer has enough money.
            if($this->money->getBalance($event->getPlayer()->getName(), true) < $price) {
                $event->getPlayer()->sendMessage(TextFormat::RED.'You do not have enough money to buy that.');
                return;
            }

            # getDamage = meta, remember to store it.
            $pinv = $event->getPlayer()->getInventory();

            # Check if buyer has enough inventory slots.
            if(!$pinv->canAddItem($item)) {
                $event->getPlayer()->sendMessage(TextFormat::RED.'You do not have enough inventory slots.');
                return;
            }

            # Check if the transaction can continue.
            if($isPlayerShop and !$this->inventory->remove($event->getPlayer()->getName(), $item)) {
                $event->getPlayer()->sendMessage(TextFormat::RED.'Could not complete the transaction.');
                return;
            }

            $pinv->addItem($item);

            # Perform transactions.
            $this->money->alterBalance($event->getPlayer()->getName(), -$price);
            if($isPlayerShop) $this->money->alterBalance($name, $price);

            $event->getPlayer()->sendMessage(TextFormat::GREEN.'Bought '.TextFormat::AQUA.$item->getName().' x'.$quantity.TextFormat::YELLOW.
                ' for '.TextFormat::AQUA.number_format($price, 2).TextFormat::GREEN.' '.
                ($price > 1 ? $this->money->getPluralName() : $this->money->getSingularName()));

            return;

        }

        # Check if the player is selling.
        if($type === 'SELL') {

            # Check if the shop owner has enough money.
            if($isPlayerShop and $this->money->getBalance($name) < $price) {
                $event->getPlayer()->sendMessage(TextFormat::RED.'The shop owner does not have enough money to complete the transaction!');
                return;
            }

            $pinv = $event->getPlayer()->getInventory();

            # Check if seller has the items and quantity.
            if(!$pinv->contains($item)) {
                $event->getPlayer()->sendMessage(TextFormat::RED.'You do not have the required items for this transaction.');
                return;
            }

            # TODO: This needs testing.
            $pinv->removeItem($item);

            # Perform player shop transaction.
            if($isPlayerShop) $this->money->alterBalance($name, -$price);
            $this->money->alterBalance($event->getPlayer()->getName(), $price);

            $event->getPlayer()->sendMessage(TextFormat::GREEN.'Sold '.TextFormat::AQUA.$item->getName().' x'.$quantity.TextFormat::YELLOW.
                ' for '.TextFormat::AQUA.number_format($price, 2).TextFormat::GREEN.' '.
                ($price > 1 ? $this->money->getPluralName() : $this->money->getSingularName()));

        }

    }

    private function breakSign(Block $block) {
        $block->onBreak(new Item(257));
        $block->getLevel()->dropItem(new Vector3(
            $block->x,
            $block->y,
            $block->z
        ), new Item(323));
    }

    private function isShopSign(Sign $sign) {

        if($sign->getText()[0] === '' or $sign->getText()[1] === ''
            or $sign->getText()[2] === '' or $sign->getText()[3] === '') return false;

        $line2 = explode(' ', $sign->getText()[1]);

        $type = strtoupper($line2[0]);
        $quantity = $line2[1];

        # Make sure the first line is either buy or sell.
        if($type !== 'BUY' AND $type !== 'SELL') return false;

        # Ensure that the second line is the quantity of items.
        if(!is_numeric($quantity)) return false;

        $quantity = intval($quantity);

        if($quantity < 1) return false;

        $price = explode(' ', $sign->getText()[2])[0];

        if(!is_numeric($price)) return false;

        $price = floatval($price);

        if($price < 0.1) return false;

        $item = $sign->getText()[3];

        if(is_numeric($item)) {
            $item = Item::get(intval($item));
        } else {
            $item = Item::fromString($item);
        }

        if($item->getName() === 'Unknown') return false;

        return true;
    }

}