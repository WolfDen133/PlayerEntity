<?php

declare(strict_types=1);

namespace WolfDen133\PlayerNPC;

use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {

    /** @var Human[] */
    public array $entities = [];

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new UpdateTask($this), 1);
    }

    public function spawnPlayer (Player $player) : void
    {
        $nbt = Entity::createBaseNBT($player->getPosition(), $player->getMotion(), $player->getYaw(), $player->getPitch());
        $nbt->setTag($player->namedtag->getCompoundTag("Skin"));
        $nbt->setString("player_name", $player->getName());

        $entity = new Human($player->getLevel(), $nbt);
        $entity->setNameTag($player->getName());
        $entity->setNameTagVisible(true);
        $entity->setPosition($player->getPosition());
        $entity->setRotation($player->getYaw(), $player->getPitch());
        $entity->setMaxHealth(99999999);
        $entity->setHealth($entity->getMaxHealth());

        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            if ($onlinePlayer->getId() == $player->getId()) continue;

            $onlinePlayer->hidePlayer($player);
            $entity->spawnTo($onlinePlayer);
        }


        $this->entities[$player->getUniqueId()->toString()] = $entity;
    }

    public function updateMovement () : void
    {
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            if (!isset($this->entities[$player->getUniqueId()->toString()])) return;
            if ($this->entities[$player->getUniqueId()->toString()]->isAlive() == false) return;

            $this->entities[$player->getUniqueId()->toString()]->getInventory()->setContents($player->getInventory()->getContents(true));
            $this->entities[$player->getUniqueId()->toString()]->getArmorInventory()->setContents($player->getArmorInventory()->getContents());
            $this->entities[$player->getUniqueId()->toString()]->getInventory()->setHeldItemIndex($player->getInventory()->getHeldItemIndex());
            $this->entities[$player->getUniqueId()->toString()]->setPosition($player->getPosition());
            $this->entities[$player->getUniqueId()->toString()]->setRotation($player->getYaw(), $player->getPitch());
            $this->entities[$player->getUniqueId()->toString()]->setSneaking($player->isSneaking());

            $pk = new RemoveActorPacket();
            $pk->entityUniqueId = $this->entities[$player->getUniqueId()->toString()]->getId();
            $player->dataPacket($pk);
        }
    }


    public function onPlayerJoinEvent (PlayerJoinEvent $event) : void
    {
        $this->getScheduler()->scheduleDelayedTask(new SpawnTask($event->getPlayer(), $this), 10);

        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $event->getPlayer()->hidePlayer($player);
        }
    }

    public function onPlayerQuitEvent (PlayerQuitEvent $event) : void
    {
        if (!isset($this->entities[$event->getPlayer()->getUniqueId()->toString()])) return;
        $this->entities[$event->getPlayer()->getUniqueId()->toString()]->close();
        unset($this->entities[$event->getPlayer()->getUniqueId()->toString()]);
    }

    public function onEntityDamageEvent (EntityDamageEvent $event) : void
    {
        if (!($event instanceof EntityDamageByEntityEvent)) return;
        if ($event->getEntity()->namedtag->hasTag("player_name")) {

            $player = $this->getServer()->getPlayer($event->getEntity()->namedtag->getString("player_name"));

            $player->setHealth($player->getHealth() - $event->getFinalDamage());

            $e = $event->getDamager();

            $x = $event->getEntity()->getX() - $e->getX();
            $z = $event->getEntity()->getZ() - $e->getZ();
            $player->knockBack($event->getDamager(), 0, $x, $z);

            $event->getEntity()->broadcastEntityEvent(ActorEventPacket::ARM_SWING);

            $player->broadcastEntityEvent(ActorEventPacket::HURT_ANIMATION);
        }
    }

    public function onPlayerDeathEvent (PlayerDeathEvent $event) : void
    {
        $this->entities[$event->getPlayer()->getUniqueId()->toString()]->getInventory()->clearAll();
        $this->entities[$event->getPlayer()->getUniqueId()->toString()]->getArmorInventory()->clearAll();
        $this->entities[$event->getPlayer()->getUniqueId()->toString()]->kill();

        unset($this->entities[$event->getPlayer()->getUniqueId()->toString()]);
    }

    public function onPlayerRespawnEvent (PlayerRespawnEvent $event) : void
    {
        $this->spawnPlayer($event->getPlayer());
    }
}
