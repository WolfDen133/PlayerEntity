<?php

namespace WolfDen133\PlayerNPC;

use pocketmine\Player;
use pocketmine\scheduler\Task;

class SpawnTask extends Task
{
    private Main $plugin;
    private Player $player;

    public function __construct(Player $player, Main $main)
    {
        $this->player = $player;
        $this->plugin = $main;
    }

    public function onRun(int $currentTick)
    {
        Echo "Spawning\n";

        $this->plugin->spawnPlayer($this->player);
    }
}