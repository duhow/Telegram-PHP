# Telegram-PHP

Another library to use Telegram bots with PHP.

- Include the **src/Autoloader.php** file.
- Create a *Telegram\Bot* object.
- Create a *Telegram\Receiver* object using the *$bot*.

```php
$bot = new Telegram\Bot("11111111:AAAAAAAAAAzzzzzzzzzzzzzzzzzzz", "MyUserBot", "The Name of Bot");
$tg = new Telegram\Receiver($bot);
```

You can create as many *Bots* and *Receivers* or *Senders* as you want.
Using *Receiver* includes a *Sender*.

# Usage

Once the page is loaded (manually or via webhook), you can send or reply the requests.

To send a message to a user or group chat:
```php
$tg->send
  ->chat("123456")
  ->text("Hello world!")
->send();
```

To reply a user command:
```php
if($tg->text_command("start")){
  $tg->send
    ->text("Hi!")
  ->send();
}
```

To reply a user message:
```php
if($tg->text_has("are you alive")){
  $tg->send
    ->text("Yes!")
  ->send();
}
```

# Examples
- [Profesor Oak](https://github.com/duhow/ProfesorOak), an assistant for Pokemon GO.
