<?php

declare(strict_types=1);

namespace venndev\vplaceholder\manager;

use pocketmine\plugin\PluginBase;
use RuntimeException;

trait ModuleManager
{

    private static string $modulePath = "modules_placeholder";

    private static function initModules(PluginBase $plugin): void
    {
        self::$modulePath = $plugin->getServer()->getDataPath() . DIRECTORY_SEPARATOR . "modules_placeholder";
        if (!is_dir(self::$modulePath)) @mkdir(self::$modulePath);
    }

    public static function runModule(string $module): void
    {
        if (!file_exists(self::$modulePath . DIRECTORY_SEPARATOR . $module)) return;
        require_once self::$modulePath . DIRECTORY_SEPARATOR . $module;
        if (!isset($author) || !isset($version)) throw new RuntimeException("Invalid module file: $module, missing author or version variable!");
    }

    public static function runModules(): void
    {
        foreach (scandir(self::$modulePath) as $module) {
            if ($module === "." || $module === "..") continue;
            self::runModule($module);
        }
    }

}