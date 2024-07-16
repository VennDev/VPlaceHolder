# VPlaceHolder
- The virion makes it possible for your server to call out your parameters through a form for notifications or words displayed on the player's interface.

# Links
- Shop Modules PlaceHolder: [Link](https://github.com/VennDev/VPlaceHolderModules/tree/main/modules_placeholder)

# Features
- ✔ Support Title, Messages, ... all related to the chat displayed to the player.
- ✔ Support Scoreboard, Bossbar, Form
- ✔ Support Name Item in inventory player or all type inventories.

# Notes
- The `modules_placeholder` directory will be created when there is a certain plugin in your server that init virion.
- That directory will help you design the available holders from the developers, and if you're interested, create a module and send it to me for review! :D

# API
- You want register one placeholder ?
- You should init virion before plugin enable.
```php
VPlaceHolder::init($this);
```
- Register with type `string|int|float` value
```php
VPlaceHolder::registerPlaceHolder('{player}', 'venndev');
$player->sendTip("Hello {player}"); // Output: Hello venndev
```
- Register with type `callable` value
```php
VPlaceHolder::registerPlaceHolder("{player}", function (string $player, int $age) {
    return "Hello $player, your age is $age";
});
$player->sendTip("{player}(VennDev, 1000)"); // Output: Hello VennDev, your age is 1000
```
```php
VPlaceHolder::registerPlaceHolder("{say_player}", function (string $player, string $message) {
    return $message . $player;
});
$player->sendTip("{player}(VennDev, 'Hello, you')"); // Output: Hello, you VennDev
```
- Register Async PlaceHolder
```php
VPlaceHolder::registerPlaceHolder("{get_money_all_players}", function (): \vennv\vapm\Async {
    return new \vennv\vapm\Async(function (): string {
        $moneyList = [];
        $players = Server::getInstance()->getOnlinePlayers();
        // TODO: Implement this

        return implode(", ", $moneyList);
    });
}, isPromise: true);
$player->sendTip("{get_money_all_players}()"); // Output: <list money all players>
```
