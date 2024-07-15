# VPlaceHolder
- The plugin makes it possible for your server to call out your parameters through a form for notifications or words displayed on the player's interface.

# API
- You want register one placeholder ?
- Register with type string|int|float value
```php
VPlaceHolder::registerPlaceHolder('{player}', 'venndev');
$player->sendTip("Hello {player}");
```
- Register with type callable value
```php
VPlaceHolder::registerPlaceHolder("{player}", function (string $player, int $age) {
    return "Hello $player, your age is $age";
});
$player->sendTip("Hello {player}(VennDev, 1000)");
```
