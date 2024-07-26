<?php

declare(strict_types=1);

namespace venndev\vplaceholder;

use pocketmine\plugin\PluginBase;
use venndev\vplaceholder\handler\PlaceHolderHandler;
use venndev\vplaceholder\manager\ModuleManager;
use vennv\vapm\VapmPMMP;

final class VPlaceHolder
{
    use PlaceHolderHandler;
    use ModuleManager;

    private static bool $enabled = false;

    public static function init(PluginBase $plugin): void
    {
        VapmPMMP::init($plugin); // Init VAPM
        if (self::$enabled) return;
        self::initModules($plugin);
        self::runModules();
        $plugin->getServer()->getPluginManager()->registerEvents(new listener\EventListener(), $plugin);
        self::$enabled = true;
    }

}