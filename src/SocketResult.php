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

class SocketResult
{
    /**
     * Socket result status.
     *
     * @var int
     */
    public $status;

    /**
     * Socket result message.
     *
     * @var string
     */
    public $message;

    /**
     * Socket it.
     *
     * @var mixed
     */
    public $id;

    /**
     * Creates an instance of {@see SocketResult} class.
     *
     * @param mixed $id
     *
     * @return static
     */
    public function __construct($id, int $status, string $message)
    {
        $this->id = $id;
        $this->status = $status;
        $this->message = $message;
    }
}
