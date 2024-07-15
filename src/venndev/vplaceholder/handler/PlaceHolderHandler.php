<?php

declare(strict_types=1);

namespace venndev\vplaceholder\handler;

use InvalidArgumentException;

trait PlaceHolderHandler
{

    private static array $placeholders = [];

    public static function registerPlaceHolder(string $placeholder, int|float|string $value): void
    {
        !isset(self::$placeholders[$placeholder]) ? self::$placeholders[$placeholder] = $value : throw new InvalidArgumentException("The placeholder $placeholder is already registered");
    }

    public static function getPlaceHolder(string $placeholder): int|float|string|null
    {
        return self::$placeholders[$placeholder] ?? null;
    }

    public static function replacePlaceHolder(string $text): string
    {
        return str_replace(array_keys(self::$placeholders), array_values(self::$placeholders), $text);
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