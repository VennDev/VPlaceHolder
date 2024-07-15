<?php

declare(strict_types=1);

namespace venndev\vplaceholder;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use venndev\vplaceholder\handler\PlaceHolderHandler;

class VPlaceHolder extends PluginBase
{
    use SingletonTrait;
    use PlaceHolderHandler;

    protected function onLoad(): void
    {
        self::setInstance($this);
    }

    protected function onEnable(): void
    {
        VPlaceHolder::registerPlaceHolder("{player}", function (string $player, int $age) {
            return "Hello $player, your age is $age";
        });
        $this->getServer()->getPluginManager()->registerEvents(new listener\EventListener($this), $this);
    }

}