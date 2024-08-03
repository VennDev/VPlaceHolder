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
    private const PLACEHOLDER_NONE = "...";
    private const TIME_TO_UPDATE = 5;
    private const TIME_TO_CLEAN = 100;

    private static int|float $lastTimeCleaned = 0;
    private static array $placeholdersNormal = [];
    private static array $placeholdersPromises = [];
    private static array $placeHoldersPromisesProcessed = [];
    private static array $timeCountDownToUpdates = [];

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
    private static function pregMatchCallable(Player $player, string $key, string $text, callable $value, bool $isAsync = false): Async|array|string
    {
        if (preg_match_all("/$key\((.*?)\)/", $text, $matches, PREG_SET_ORDER)) {
            if ($isAsync) {
                return new Async(function () use ($player, $matches, $value, $text, $key): array {
                    $changes = [];
                    foreach ($matches as $match) {
                        if (empty($match[1]) || !is_string($match[1])) throw new InvalidArgumentException("The placeholder $key must have a parameter");
                        $params = preg_split("/, (?=(?:[^']*'[^']*')*[^']*$)/", $match[1]);
                        $strReplaceList = str_replace(['"', "'"], '', $params);
                        $strReplaceList[0] === self::PLACEHOLDER_AUTO_KEY ? $replacement = Async::await($value($player->getName(), ...$strReplaceList)) : $replacement = Async::await($value(...$strReplaceList));
                        $text = str_replace($match[0], $replacement, $text);
                        $changes[$match[0]] = $replacement;
                    }
                    return $changes;
                });
            } else {
                $changes = [];
                foreach ($matches as $match) {
                    if (empty($match[1]) || !is_string($match[1])) throw new InvalidArgumentException("The placeholder $key must have a parameter");
                    $params = preg_split("/, (?=(?:[^']*'[^']*')*[^']*$)/", $match[1]);
                    $strReplaceList = str_replace(['"', "'"], '', $params);
                    $strReplaceList[0] === self::PLACEHOLDER_AUTO_KEY ? $replacement = $value($player->getName(), ...$strReplaceList) : $replacement = $value(...$strReplaceList);
                    $text = str_replace($match[0], $replacement, $text);
                    $changes[$match[0]] = $replacement;
                }
                return $changes;
            }
        }
        return $text;
    }

    /**
     * @throws Throwable
     */
    public static function closureOnePlaceHolder(Player $player, string $text, callable $callable): void
    {
        $replacePromise = str_replace(array_keys(self::$placeholdersPromises), self::PLACEHOLDER_NONE, $text);
        if ($replacePromise !== $text) {
            new Async(function () use ($player, $text, $callable): void {
                foreach (self::$placeholdersPromises as $key => $value) {
                    $textReplace = Async::await(self::pregMatchCallable($player, $key, $text, $value, true));
                    if (is_string($textReplace) && str_replace(array_keys(self::$placeholdersPromises), self::PLACEHOLDER_NONE, $textReplace) === $text) break;
                    if (is_array($textReplace)) $textReplace = str_replace(array_keys($textReplace), array_values($textReplace), $text);
                    $text = $textReplace;
                }
                $callable($text);
            });
        } else {
            new Async(function () use ($player, $text, $callable): void {
                foreach (self::$placeholdersNormal as $key => $value) {
                    is_callable($value) ? $textReplace = self::pregMatchCallable($player, $key, $text, $value) : $textReplace = str_replace($key, $value, $text);
                    if (is_string($textReplace) && str_replace(array_keys(self::$placeholdersNormal), self::PLACEHOLDER_NONE, $textReplace) === $text) break;
                    if (is_array($textReplace)) $textReplace = str_replace(array_keys($textReplace), array_values($textReplace), $text);
                    $text = $textReplace;
                    FiberManager::wait();
                }
                $callable($text);
            });
        }
    }

    /**
     * @throws Throwable
     */
    public static function replacePlaceHolder(Player $player, string $text): string
    {
        $replacePromise = str_replace(array_keys(self::$placeholdersPromises), self::PLACEHOLDER_NONE, $text);
        if ($replacePromise !== $text) {
            $lastText = $text;
            $playerXuid = $player->getXuid();
            $fnRegister = function(string $placeHolder, string $value) use ($playerXuid): void {
                self::$placeHoldersPromisesProcessed[$playerXuid][$placeHolder] = $value;
            };
            $fnUpdate = function () use ($player, $playerXuid, $lastText, $text, $fnRegister): void {
                new Async(function () use ($player, $playerXuid, $lastText, $text, $fnRegister): void {
                    foreach (self::$placeholdersPromises as $key => $value) {
                        $textReplace = Async::await(self::pregMatchCallable($player, $key, $text, $value, true));
                        if (is_string($textReplace) && str_replace(array_keys(self::$placeholdersPromises), self::PLACEHOLDER_NONE, $textReplace) === $text) break;
                        if (is_array($textReplace)) {
                            foreach ($textReplace as $case => $valueT) {
                                $fnRegister($case, $valueT);
                                FiberManager::wait();
                            }
                        }
                    }
                    foreach (self::$placeholdersNormal as $key => $value) {
                        is_callable($value) ? $textReplace = self::pregMatchCallable($player, $key, $text, $value) : $textReplace = str_replace($key, $value, $text);
                        if (is_string($textReplace) && str_replace(array_keys(self::$placeholdersNormal), self::PLACEHOLDER_NONE, $textReplace) === $text) break;
                        if (is_array($textReplace)) {
                            foreach ($textReplace as $case => $valueT) {
                                $fnRegister($case, $valueT);
                                FiberManager::wait();
                            }
                        }
                        FiberManager::wait();
                    }
                });
            };
            if (microtime(true) - self::$lastTimeCleaned > self::TIME_TO_CLEAN) {
                self::$lastTimeCleaned = microtime(true);
                self::$placeHoldersPromisesProcessed = [];
            }
            if (!isset(self::$placeHoldersPromisesProcessed[$playerXuid])) self::$placeHoldersPromisesProcessed[$playerXuid] = [];
            if (isset(self::$placeHoldersPromisesProcessed[$playerXuid])) {
                if (!isset(self::$timeCountDownToUpdates[$playerXuid])) {
                    self::$timeCountDownToUpdates[$playerXuid] = microtime(true);
                    $fnUpdate();
                } elseif (microtime(true) - self::$timeCountDownToUpdates[$playerXuid] > self::TIME_TO_UPDATE) {
                    self::$timeCountDownToUpdates[$playerXuid] = microtime(true);
                    $fnUpdate();
                }
            }
            $text = str_replace(array_keys(self::$placeHoldersPromisesProcessed[$playerXuid]), array_values(self::$placeHoldersPromisesProcessed[$playerXuid]), $text);
            if ($text === $lastText) return $replacePromise;
        } else {
            foreach (self::$placeholdersNormal as $key => $value) {
                if (is_callable($value)) {
                    $replaceCallable = self::pregMatchCallable($player, $key, $text, $value);
                    if (is_string($replaceCallable)) {
                        $text = $replaceCallable;
                    } elseif (is_array($replaceCallable)) {
                        $text = str_replace(array_keys($replaceCallable), array_values($replaceCallable), $text);
                    }
                } else {
                    $text = str_replace($key, $value, $text);
                }
                if (str_replace(array_keys(self::$placeholdersNormal), self::PLACEHOLDER_NONE, $text) === $text) break;
            }
        }
        return $text;
    }

}