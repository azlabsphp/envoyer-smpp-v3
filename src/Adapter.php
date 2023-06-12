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

namespace Drewlabs\Envoyer\Drivers\Smpp;

use Drewlabs\Envoyer\Contracts\ClientInterface;
use Drewlabs\Envoyer\Contracts\ClientSecretKeyAware;
use Drewlabs\Envoyer\Contracts\NotificationInterface;
use Drewlabs\Envoyer\Contracts\NotificationResult;
use Drewlabs\Envoyer\Contracts\ServerConfigInterface;
use Drewlabs\Net\Sockets\SocketStream;

class Adapter implements ClientInterface
{
    /**
     * @var ClientSecretKeyAware&ServerConfigInterface
     */
    private $server;

    /**
     * Error handler to be used when running in debug mode.
     *
     * @var \Closure
     */
    private $debugLogger;

    /**
     * @var bool
     */
    private $debugging = false;

    /**
     * Creates new class instance.
     */
    public function __construct(ClientSecretKeyAware $server)
    {
        $this->server = $server;
    }

    public function sendRequest(NotificationInterface $instance): NotificationResult
    {
        try {
            // Write the smpp transport initialization code
            if ($this->debugging) {
                SocketStream::debug();
                Client::debug();
                SocketStream::setDefaultDebugLogger($this->debugLogger ?? 'error_log');
            }
            SocketStream::setForceIpv4(true);
            Client::setNullTerminatingString();
            // #region Create transport instance
            $transport = new SocketStream($this->server->getHost(), (int) $this->server->getHostPort(), false, $this->debugLogger);
            $transport->setSendTimeout(10000);
            $transport->setRecvTimeout(10000);
            $transport->open();
            // #endregion Create transport instance

            // Create SMPP Client instance
            $smpp = new Client($transport);

            // Bind the SMPP transmitter with client id and client secret
            $smpp->bindTransmitter($this->server->getClientId(), $this->server->getClientSecret());

            // GSM Encode the message
            $encodedMessage = GsmEncoder::utf8_to_gsm0338((string) $instance->getContent());

            // Create GSM addresses
            $from = new Address($instance->getSender()->__toString(), SMPP::TON_ALPHANUMERIC);
            $to = new Address($instance->getReceiver()->__toString(), SMPP::NPI_E164);

            // Send the GSM message
            return new NotificationResult($smpp->sendSMS($from, $to, $encodedMessage));

        } catch (\Exception $e) {
            // Write a better log handler later
            throw new \RuntimeException($e->getMessage());
        } finally {
            // Close connection
            $smpp->close();
        }
    }

    /**
     * Set the debug handler to use when running in debug mode.
     *
     * @return static
     */
    public function setLogger(\Closure $callback)
    {
        $this->debugLogger = $callback;

        return $this;
    }

    /**
     * Executes the provider in debug mode.
     *
     * @return static
     */
    public function debug()
    {
        $this->debugging = true;

        return $this;
    }
}
