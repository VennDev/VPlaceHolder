<?php

declare(strict_types=1);

namespace venndev\vplaceholder\handler;

use Throwable;
use InvalidArgumentException;
use pocketmine\player\Player;
use vennv\vapm\Async;
use vennv\vapm\FiberManager;

trait PlaceHolderHandler
{

    private const PLACEHOLDER_AUTO_KEY = "A";

    private static int|float $lastTimeCleaned = 0;
    private static array $placeholdersNormal = [];
    private static array $placeholdersPromises = [];
    private static array $placeHoldersPromisesProcessed = [];

    public static function getAllPlaceHolders(): array
    {
        return array_merge(self::$placeholdersNormal, self::$placeholdersPromises);
    }

    public static function getPlaceHolder(string $placeholder): int|float|string|callable
    {
        if (isset(self::$placeholdersNormal[$placeholder])) {
            return self::$placeholdersNormal[$placeholder];
        } elseif (isset(self::$placeholdersPromises[$placeholder])) {
            return self::$placeholdersPromises[$placeholder];
        }
        throw new InvalidArgumentException("The placeholder $placeholder is not registered!");
    }

    public static function isPlaceHolderRegistered(string $placeholder): bool
    {
        return isset(self::$placeholdersNormal[$placeholder]) || isset(self::$placeholdersPromises[$placeholder]);
    }

    public static function registerPlaceHolder(string $placeholder, int|float|string|callable $value, bool $isPromise = false): void
    {
        if (isset(self::$placeholdersNormal[$placeholder]) || isset(self::$placeholdersPromises[$placeholder])) throw new InvalidArgumentException("The placeholder $placeholder is already registered!");
        if ($isPromise && is_callable($value)) {
            self::$placeholdersPromises[$placeholder] = $value;
        } elseif (!$isPromise) {
            self::$placeholdersNormal[$placeholder] = $value;
        } else {
            throw new InvalidArgumentException("The placeholder $placeholder must be a callable!");
        }
    }

    public static function unregisterPlaceHolder(string $placeholder): void
    {
        if (isset(self::$placeholdersNormal[$placeholder])) {
            unset(self::$placeholdersNormal[$placeholder]);
        } elseif (isset(self::$placeholdersPromises[$placeholder])) {
            unset(self::$placeholdersPromises[$placeholder]);
        }
    }

    /**
     * @throws Throwable
     */
    public static function closureOnePlaceHolder(Player $player, string $key, string $text, callable $callable): void
    {
        $replacePromise = str_replace(array_keys(self::$placeholdersPromises), "...", $text);
        if ($replacePromise !== $text) {
            new Async(function () use ($player, $text, $callable): void {
                foreach (self::$placeholdersPromises as $key => $value) {
                    $text = Async::await(self::pregMatchCallable($player, $key, $text, $value, true));
                    if (str_replace(array_keys(self::$placeholdersPromises), "...", $text) === $text) break;
                }
                $callable($text);
            });
        } else {
            new Async(function () use ($player, $text, $callable): void {
                foreach (self::$placeholdersNormal as $key => $value) {
                    is_callable($value) ? $text = self::pregMatchCallable($player, $key, $text, $value) : $text = str_replace($key, $value, $text);
                    if (str_replace(array_keys(self::$placeholdersNormal), "...", $text) === $text) break;
                    FiberManager::wait();
                }
                $callable($text);
            });
        }
    }

    /**
     * @throws Throwable
     */
    private static function pregMatchCallable(Player $player, string $key, string $text, callable $value, bool $isAsync = false): Async|string
    {
        if (preg_match_all("/$key\((.*?)\)/", $text, $matches, PREG_SET_ORDER)) {
            if ($isAsync) {
                return new Async(function () use ($player, $matches, $value, $text, $key): string {
                    foreach ($matches as $match) {
                        if (empty($match[1]) || !is_string($match[1])) throw new InvalidArgumentException("The placeholder $key must have a parameter");
                        $params = preg_split("/, (?=(?:[^']*'[^']*')*[^']*$)/", $match[1]);
                        $strReplaceList = str_replace(['"', "'"], '', $params);
                        $strReplaceList[0] === self::PLACEHOLDER_AUTO_KEY ? $replacement = Async::await($value($player->getName(), ...$strReplaceList)) : $replacement = Async::await($value(...$strReplaceList));
                        $text = str_replace($match[0], $replacement, $text);
                    }
                    return $text;
                });
            } else {
                foreach ($matches as $match) {
                    if (empty($match[1]) || !is_string($match[1])) throw new InvalidArgumentException("The placeholder $key must have a parameter");
                    $params = preg_split("/, (?=(?:[^']*'[^']*')*[^']*$)/", $match[1]);
                    $strReplaceList = str_replace(['"', "'"], '', $params);
                    $strReplaceList[0] === self::PLACEHOLDER_AUTO_KEY ? $replacement = $value($player->getName(), ...$strReplaceList) : $replacement = $value(...$strReplaceList);
                    $text = str_replace($match[0], $replacement, $text);
                }
            }
        }

        return $text;
    }

    /**
     * @throws Throwable
     */
    public static function replacePlaceHolder(Player $player, string $text): string
    {
        $replacePromise = str_replace(array_keys(self::$placeholdersPromises), "...", $text);
        if ($replacePromise !== $text) {
            $lastText = $text;
            $playerXuid = $player->getXuid();
            if (microtime(true) - self::$lastTimeCleaned > 100) {
                self::$lastTimeCleaned = microtime(true);
                self::$placeHoldersPromisesProcessed = [];
            }
            if (!isset(self::$placeHoldersPromisesProcessed[$playerXuid])) self::$placeHoldersPromisesProcessed[$playerXuid] = [];
            if (!isset(self::$placeHoldersPromisesProcessed[$playerXuid][$text])) {
                new Async(function () use ($player, $playerXuid, $lastText, $text): void {
                    foreach (self::$placeholdersPromises as $key => $value) {
                        $text = Async::await(self::pregMatchCallable($player, $key, $text, $value, true));
                        if (str_replace(array_keys(self::$placeholdersPromises), "...", $text) === $text) break;
                    }
                    foreach (self::$placeholdersNormal as $key => $value) {
                        is_callable($value) ? $text = self::pregMatchCallable($player, $key, $text, $value) : $text = str_replace($key, $value, $text);
                        if (str_replace(array_keys(self::$placeholdersNormal), "...", $text) === $text) break;
                        FiberManager::wait();
                    }
                    self::$placeHoldersPromisesProcessed[$playerXuid][$lastText] = [$text, microtime(true)];
                });
            } else {
                if (microtime(true) - self::$placeHoldersPromisesProcessed[$playerXuid][$text][1] > 10) {
                    unset(self::$placeHoldersPromisesProcessed[$playerXuid][$text]);
                } else {
                    $text = self::$placeHoldersPromisesProcessed[$playerXuid][$text][0];
                }
            }

            if ($text === $lastText) return $replacePromise;
        } else {
            foreach (self::$placeholdersNormal as $key => $value) {
                is_callable($value) ? $text = self::pregMatchCallable($player, $key, $text, $value) : $text = str_replace($key, $value, $text);
                if (str_replace(array_keys(self::$placeholdersNormal), "...", $text) === $text) break;
            }
        }

        return $text;
    }

}