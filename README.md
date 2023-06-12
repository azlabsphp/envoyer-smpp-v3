# SMPP Driver

The library is an implementation of `drewlabs/envoyer` driver or client interface that interact with `Short Message Service` server for sending sms through [SMPP v3.4](http://www.smsforum.net/SMPP_v3_4_Issue1_2.zip) protocol.

In addition to the client, this lib also contains an encoder for converting UTF-8 text to the GSM 03.38 encoding.

**Note** This lib requires the [sockets](http://www.php.net/manual/en/book.sockets.php) PHP-extension, and is not supported on Windows

## Usage

```php
use Drewlabs\Envoyer\Drivers\Smpp\Adapter;
use Drewlabs\Envoyer\Drivers\Smpp\ClientSecretKeyServer;
use Drewlabs\Envoyer\Message;

$config = require __DIR__.'/config/drivers.php';

// Create
$adapter = new Adapter(new ClientSecretKeyServer($config['host'], intval($config['port']), $config['user'], $config['password']));
$message = Message::new()->from('22990667812')->to('22890667723')->content('Hi!');

// Send the SMPP request
$result = $adapter->sendRequest($message);
```
