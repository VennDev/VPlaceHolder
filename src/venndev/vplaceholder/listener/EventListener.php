<?php

declare(strict_types=1);

namespace venndev\vplaceholder\listener;

use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\network\mcpe\protocol\SetTitlePacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\CommandRequestPacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\Server;
use venndev\vplaceholder\VPlaceHolder;
use Throwable;
use vennv\vapm\FiberManager;
use vennv\vapm\Promise;

final readonly class EventListener implements Listener
{

    /**
     * @throws Throwable
     */
    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        $targets = $event->getTargets();
        $packets = $event->getPackets();
        foreach ($targets as $target) {
            $player = $target->getPlayer();
            if ($player !== null && $player->isConnected()) {
                foreach ($packets as $packet) {
                    if ($packet instanceof SetScorePacket) foreach ($packet->entries as $entry) try {
                        $entry->customName = VPlaceHolder::replacePlaceHolder($player, $entry->customName);
                    } catch (Throwable) {
                        Server::getInstance()->getLogger()->Debug("It's just that the packets have not been sent carefully to the player." . $packet->getName());
                    }
                    if ($packet instanceof SetDisplayObjectivePacket) $packet->displayName = VPlaceHolder::replacePlaceHolder($player, $packet->displayName);
                    if ($packet instanceof TextPacket) $packet->message = VPlaceHolder::replacePlaceHolder($player, $packet->message);
                    if ($packet instanceof SetTitlePacket) $packet->text = VPlaceHolder::replacePlaceHolder($player, $packet->text);
                    if ($packet instanceof BossEventPacket) try {
                        $packet->title = VPlaceHolder::replacePlaceHolder($player, $packet->title);
                    } catch (Throwable) {
                        Server::getInstance()->getLogger()->Debug("It's just that the packets have not been sent carefully to the player." . $packet->getName());
                    }
                    if ($packet instanceof ModalFormRequestPacket) {
                        $data = json_decode($packet->formData, true);
                        $data["title"] = VPlaceHolder::replacePlaceHolder($player, $data["title"]);
                        if (is_string($data["content"])) $data["content"] = VPlaceHolder::replacePlaceHolder($player, $data["content"]);
                        if (is_array($data["content"])) foreach ($data["content"] as $key => $value) if (isset($data["content"][$key]["text"])) $data["content"][$key]["text"] = VPlaceHolder::replacePlaceHolder($player, $data["content"][$key]["text"]);
                        if (isset($data["buttons"])) foreach ($data["buttons"] as $key => $value) if (isset($data["buttons"][$key]["text"])) $data["buttons"][$key]["text"] = VPlaceHolder::replacePlaceHolder($player, $data["buttons"][$key]["text"]);
                        if (isset($data["button1"])) $data["button1"] = VPlaceHolder::replacePlaceHolder($player, $data["button1"]);
                        if (isset($data["button2"])) $data["button2"] = VPlaceHolder::replacePlaceHolder($player, $data["button2"]);
                        $packet->formData = json_encode($data);
                    }
                }
            }
        }
    }

    /**
     * @throws Throwable
     */
    public function onDataPacketReceive(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        $origin = $event->getOrigin();
        $player = $origin->getPlayer();
        if ($player !== null && $player->isConnected()) {
            if ($packet instanceof TextPacket) $packet->message = VPlaceHolder::replacePlaceHolder($player, $packet->message);
            if ($packet instanceof CommandRequestPacket) $packet->command = VPlaceHolder::replacePlaceHolder($player, $packet->command);
            if ($packet instanceof BossEventPacket) try {
                $packet->title = VPlaceHolder::replacePlaceHolder($player, $packet->title);
            } catch (Throwable) {
                Server::getInstance()->getLogger()->Debug("It's just that the packets have not been sent carefully to the player." . $packet->getName());
            }
            new Promise(function ($resolve, $reject) use ($player): void {
                try {
                    if ($player instanceof Player && $player->getCurrentWindow() !== null) {
                        foreach ($player->getCurrentWindow()->getContents() as $slot => $item) {
                            try {
                                $itemClone = clone $item;
                                $itemClone->setCustomName(VPlaceHolder::replacePlaceHolder($player, $itemClone->getName()));
                                $lore = $itemClone->getLore();
                                foreach ($lore as $key => $value) {
                                    $lore[$key] = VPlaceHolder::replacePlaceHolder($player, $value);

                                    FiberManager::wait();
                                }
                                $itemClone->setLore($lore);
                                $player->getCurrentWindow()->setItem($slot, $itemClone);
                            } catch (Throwable) {
                                Server::getInstance()->getLogger()->Debug("It's just that the items have not been sent carefully to the player.");
                            }

                            FiberManager::wait();
                        }
                    }

                    $resolve();
                } catch (Throwable $e) {
                    $reject($e);
                }
            });
        }
    }

}