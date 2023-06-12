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

/**
 * Primitive class for encapsulating PDUs.
 *
 * @author hd@onlinecity.dk
 */
class PDU
{
    public $id;
    public $status;
    public $sequence;
    public $body;

    /**
     * Create new generic PDU object.
     *
     * @param int    $id
     * @param int    $status
     * @param int    $sequence
     * @param string $body
     */
    public function __construct($id, $status, $sequence, $body)
    {
        $this->id = $id;
        $this->status = $status;
        $this->sequence = $sequence;
        $this->body = $body;
    }
}
