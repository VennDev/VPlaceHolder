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
use venndev\vplaceholder\VPlaceHolder;
use Throwable;

final readonly class EventListener implements Listener
{

    public function __construct(
        private VPlaceHolder $plugin
    )
    {
        // Register events here
    }

    /**
     * @throws Throwable
     */
    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        $packets = $event->getPackets();
        foreach ($packets as $packet) {
            if ($packet instanceof SetScorePacket) foreach ($packet->entries as $entry) try { $entry->customName = $this->plugin::replacePlaceHolder($entry->customName); } catch (Throwable) {}
            if ($packet instanceof SetDisplayObjectivePacket) $packet->displayName = $this->plugin::replacePlaceHolder($packet->displayName);
            if ($packet instanceof TextPacket) $packet->message = $this->plugin::replacePlaceHolder($packet->message);
            if ($packet instanceof SetTitlePacket) $packet->text = $this->plugin::replacePlaceHolder($packet->text);
            if ($packet instanceof BossEventPacket) try { $packet->title = $this->plugin::replacePlaceHolder($packet->title); } catch (Throwable) {}
            if ($packet instanceof ModalFormRequestPacket) {
                $data = json_decode($packet->formData, true);
                $data["title"] = $this->plugin::replacePlaceHolder($data["title"]);
                if (is_string($data["content"])) $data["content"] = $this->plugin::replacePlaceHolder($data["content"]);
                if (is_array($data["content"])) foreach ($data["content"] as $key => $value) if (isset($data["content"][$key]["text"])) $data["content"][$key]["text"] = $this->plugin::replacePlaceHolder($data["content"][$key]["text"]);
                if (isset($data["buttons"])) foreach ($data["buttons"] as $key => $value) if (isset($data["buttons"][$key]["text"])) $data["buttons"][$key]["text"] = $this->plugin::replacePlaceHolder($data["buttons"][$key]["text"]);
                if (isset($data["button1"])) $data["button1"] = $this->plugin::replacePlaceHolder($data["button1"]);
                if (isset($data["button2"])) $data["button2"] = $this->plugin::replacePlaceHolder($data["button2"]);
                $packet->formData = json_encode($data);
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
        if ($packet instanceof TextPacket) $packet->message = $this->plugin::replacePlaceHolder($packet->message);
        if ($packet instanceof CommandRequestPacket) $packet->command = $this->plugin::replacePlaceHolder($packet->command);
        if ($packet instanceof BossEventPacket) try { $packet->title = $this->plugin::replacePlaceHolder($packet->title); } catch (Throwable) {}
        if ($player instanceof Player && $player->getCurrentWindow() !== null) foreach ($player->getCurrentWindow()->getContents() as $item) try { $item->setCustomName($this->plugin::replacePlaceHolder($item->getName())); } catch (Throwable) {}
    }

}