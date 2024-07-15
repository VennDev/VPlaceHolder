<?php

declare(strict_types=1);

namespace venndev\vplaceholder\handler;

use InvalidArgumentException;

trait PlaceHolderHandler
{

    private static array $placeholders = [];

    public static function registerPlaceHolder(string $placeholder, int|float|string|callable $value): void
    {
        !isset(self::$placeholders[$placeholder]) ? self::$placeholders[$placeholder] = $value : throw new InvalidArgumentException("The placeholder $placeholder is already registered");
    }

    public static function getPlaceHolder(string $placeholder): int|float|string|callable|null
    {
        return self::$placeholders[$placeholder] ?? null;
    }

    public static function replacePlaceHolder(string $text): string
    {
        return array_reduce(array_keys(self::$placeholders), function ($text, $key) {
            $value = self::$placeholders[$key];
            if (is_callable($value)) {
                return preg_replace_callback("/$key\((.*?)\)/", function ($matches) use ($value) {
                    return call_user_func_array($value, explode(",", $matches[1]));
                }, $text);
            }
            return str_replace($key, $value, $text);
        }, $text);
    }

    public static function getPlaceHolders(): array
    {
        return self::$placeholders;
    }

    public static function unregisterPlaceHolder(string $placeholder): void
    {
        unset(self::$placeholders[$placeholder]);
    }

}