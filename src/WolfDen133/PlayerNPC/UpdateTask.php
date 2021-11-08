<?php

namespace WolfDen133\PlayerNPC;

use pocketmine\scheduler\Task;

class UpdateTask extends Task
{
    public Main $plugin;

    public function __construct(Main $main)
    {
        $this->plugin = $main;
    }

    public function onRun(int $currentTick)
    {
        $this->plugin->updateMovement();
    }
}