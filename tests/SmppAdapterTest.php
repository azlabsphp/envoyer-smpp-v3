<?php

declare(strict_types=1);

/*
 * This file is part of the drewlabs namespace.
 *
 * (c) Sidoine Azandrew <azandrewdevelopper@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drewlabs\Notifications\Smpp\Test;

use Drewlabs\Envoyer\Contracts\NotificationResult;
use Drewlabs\Envoyer\Drivers\Smpp\Adapter;
use Drewlabs\Envoyer\Drivers\Smpp\ClientSecretKeyServer;
use Drewlabs\Envoyer\Message;
use PHPUnit\Framework\TestCase;

class SmppAdapterTest extends TestCase
{
    public function test_smpp_adapter_send_request()
    {
        $config = require __DIR__.'/configs/drivers.php';
        
        // Create
        $adapter = new Adapter(new ClientSecretKeyServer($config['host'], intval($config['port']), $config['user'], $config['password']));
        $message = Message::new()->from('22990667812')->to('22890667723')->content('Hi!');

        // Act
        $result = $adapter->sendRequest($message);

        // Assert
        $this->assertInstanceOf(NotificationResult::class, $result);
    }
}
