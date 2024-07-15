<?php

declare(strict_types=1);

namespace venndev\vplaceholder;

use pocketmine\plugin\PluginBase;
use venndev\vplaceholder\handler\PlaceHolderHandler;

final class VPlaceHolder
{
    use PlaceHolderHandler;

    private static bool $enabled = false;

    public static function init(PluginBase $plugin): void
    {
        if (self::$enabled) return;
        $plugin->getServer()->getPluginManager()->registerEvents(new listener\EventListener(), $plugin);
        self::$enabled = true;
    }

}