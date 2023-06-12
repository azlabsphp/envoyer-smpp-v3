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

use Drewlabs\Net\Sockets\SocketStreamInterface;

/**
 * Class for receiving or sending sms through SMPP protocol.
 * This is a reduced implementation of the SMPP protocol, and as such not all features will or ought to be available.
 * The purpose is to create a lightweight and simplified SMPP client.
 *
 * @author hd@onlinecity.dk, paladin
 *
 * @see http://en.wikipedia.org/wiki/Short_message_peer-to-peer_protocol - SMPP 3.4 protocol specification
 * Derived from work done by paladin, see: http://sourceforge.net/projects/phpsmppapi/
 *
 * Copyright (C) 2011 OnlineCity
 * Copyright (C) 2006 Paladin
 *
 * This library is free software; you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Lesser General Public License for more details.
 *
 * This license can be read at: http://www.opensource.org/licenses/lgpl-2.1.php
 */
class Client
{
    /**
     * Use sar_msg_ref_num and sar_total_segments with 16 bit tags.
     *
     * @var int
     */
    public const CSMS_16BIT_TAGS = 0;

    /**
     * Use message payload for CSMS.
     *
     * @var int
     */
    public const CSMS_PAYLOAD = 1;

    /**
     * Embed a UDH in the message with 8-bit reference.
     *
     * @var int
     */
    public const CSMS_8BIT_UDH = 2;

    // SMPP bind parameters
    public static $system_type = 'WWW';
    public static $interface_version = 0x34;
    public static $addr_ton = 0;
    public static $addr_npi = 0;
    public static $address_range = '';

    // ESME transmitter parameters
    public static $sms_service_type = '';
    public static $sms_esm_class = 0x00;
    public static $sms_protocol_id = 0x00;
    public static $sms_priority_flag = 0x00;
    public static $sms_registered_delivery_flag = 0x00;
    public static $sms_replace_if_present_flag = 0x00;
    public static $sms_sm_default_msg_id = 0x00;

    /**
     * SMPP v3.4 says octect string are "not necessarily NULL terminated".
     * Switch to toggle this feature.
     *
     * @var bool
     */
    public static $null_terminate_octets = true;

    /**
     * Client csms method.
     *
     * @var int
     */
    public static $csms_method = self::CSMS_16BIT_TAGS;

    /**
     * PDU queue data structure.
     *
     * @var array
     */
    protected $pdu_queue;

    /**
     * Socket transport instance.
     *
     * @var SocketStreamInterface
     */
    protected $transport;

    /**
     * Debug logger instance.
     *
     * @var \Closure|callable
     */
    protected $debugLogger;

    /**
     * Reconnect mode.
     *
     * @var bool
     */
    protected $mode;

    /**
     * Sequence number.
     *
     * @var int
     */
    protected $sequence_number;

    /**
     * SAR message reference number.
     *
     * @var int
     */
    protected $sar_msg_ref_num;

    /**
     * @var bool
     */
    private static $debugMode = false;

    /**
     * Authentication user name.
     *
     * @var string
     */
    private $login;

    /**
     * Authentication password.
     *
     * @var string
     */
    private $pass;

    /**
     * Creates an instance of {@see Client} class.
     *
     * @return static
     */
    public function __construct(SocketStreamInterface $transport, callable $debugLogger = null)
    {
        $this->sequence_number = 1;
        $this->pdu_queue = [];
        $this->transport = $transport;
        $this->debugLogger = $debugLogger ?? 'error_log';
        $this->mode = null;
    }

    /**
     * Binds the receiver. One object can be bound only as receiver or only as trancmitter.
     *
     * @param string $login - ESME system_id
     * @param string $pass  - ESME password
     *
     * @throws TransportException
     */
    public function bindReceiver($login, $pass)
    {
        if (!$this->transport->isOpen()) {
            return false;
        }
        if (static::$debugMode) {
            $this->log('Binding receiver...');
        }
        // #region Configure transmitter values
        $this->mode = 'receiver';
        $this->login = $login;
        $this->pass = $pass;
        // #endregion Configure transmitter values

        $response = $this->_bind($this->login, $this->pass, SMPP::BIND_RECEIVER);

        if (static::$debugMode) {
            $this->log('Binding status  : '.$response->status);
        }
    }

    /**
     * Binds the transmitter. One object can be bound only as receiver or only as trancmitter.
     *
     * @param string $login - ESME system_id
     * @param string $pass  - ESME password
     *
     * @throws TransportException
     */
    public function bindTransmitter($login, $pass)
    {
        if (!$this->transport->isOpen()) {
            return false;
        }
        if (static::$debugMode) {
            $this->log('Binding transmitter...');
        }

        // #region Configure client properties
        $this->mode = 'transmitter';
        $this->login = $login;
        $this->pass = $pass;
        // #endregion Configure client properties

        $response = $this->_bind($this->login, $this->pass, SMPP::BIND_TRANSMITTER);

        if (static::$debugMode) {
            $this->log('Binding status  : '.$response->status);
        }
    }

    /**
     * Closes the session on the SMSC server.
     */
    public function close()
    {
        if (!$this->transport->isOpen()) {
            return;
        }
        if (static::$debugMode) {
            $this->log('Unbinding...');
        }

        $response = $this->sendCommand(SMPP::UNBIND, '');

        if (static::$debugMode) {
            $this->log('Unbind status   : '.$response->status);
        }
        $this->transport->close();
    }

    /**
     * Parse a timestring as formatted by SMPP v3.4 section 7.1.
     * Returns an unix timestamp if $newDates is false or DateTime/DateInterval is missing,
     * otherwise an object of either DateTime or DateInterval is returned.
     *
     * @param string $input
     * @param bool   $newDates
     *
     * @return mixed
     */
    public function parseSmppTime($input, $newDates = true)
    {
        // Check for support for new date classes
        if (!class_exists('DateTime') || !class_exists('DateInterval')) {
            $newDates = false;
        }

        $numMatch = preg_match('/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{1})(\\d{2})([R+-])$/', $input, $matches);
        if (!$numMatch) {
            return null;
        }
        [$whole, $y, $m, $d, $h, $i, $s, $t, $n, $p] = $matches;

        // Use strtotime to convert relative time into a unix timestamp
        if ('R' === $p) {
            if ($newDates) {
                $spec = 'P';
                if ($y) {
                    $spec .= $y.'Y';
                }
                if ($m) {
                    $spec .= $m.'M';
                }
                if ($d) {
                    $spec .= $d.'D';
                }
                if ($h || $i || $s) {
                    $spec .= 'T';
                }
                if ($h) {
                    $spec .= $h.'H';
                }
                if ($i) {
                    $spec .= $i.'M';
                }
                if ($s) {
                    $spec .= $s.'S';
                }

                return new \DateInterval($spec);
            }

            return strtotime("+$y year +$m month +$d day +$h hour +$i minute $s +second");
        }
        $offsetHours = floor($n / 4);
        $offsetMinutes = ($n % 4) * 15;
        $time = sprintf('20%02s-%02s-%02sT%02s:%02s:%02s%s%02s:%02s', $y, $m, $d, $h, $i, $s, $p, $offsetHours, $offsetMinutes); // Not Y3K safe
        if ($newDates) {
            return new \DateTimeImmutable($time);
        }

        return strtotime($time);
    }

    /**
     * Query the SMSC about current state/status of a previous sent SMS.
     * You must specify the SMSC assigned message id and source of the sent SMS.
     * Returns an associative array with elements: message_id, final_date, message_state and error_code.
     * 	message_state would be one of the SMPP::STATE_* constants. (SMPP v3.4 section 5.2.28)
     * 	error_code depends on the telco network, so could be anything.
     *
     * @param string $messageid
     *
     * @return array
     */
    public function queryStatus($messageid, Address $source)
    {
        $pduBody = pack('a'.(\strlen($messageid) + 1).'cca'.(\strlen($source->value) + 1), $messageid, $source->typeOfNumber, $source->numberingPlanIndicator, $source->value);
        $reply = $this->sendCommand(SMPP::QUERY_SM, $pduBody);
        if (!$reply || SMPP::ESME_ROK !== $reply->status) {
            return null;
        }

        // Parse reply
        $posId = strpos($reply->body, "\0", 0);
        $posDate = strpos($reply->body, "\0", $posId + 1);
        $data = [];
        $data['message_id'] = substr($reply->body, 0, $posId);
        $data['final_date'] = substr($reply->body, $posId, $posDate - $posId);
        $data['final_date'] = $data['final_date'] ? $this->parseSmppTime(trim($data['final_date'])) : null;
        $status = unpack('cmessage_state/cerror_code', substr($reply->body, $posDate + 1));

        return array_merge($data, $status);
    }

    /**
     * Read one SMS from SMSC. Can be executed only after bindReceiver() call.
     * This method blocks. Method returns on socket timeout or enquire_link signal from SMSC.
     *
     * @return sms associative array or false when reading failed or no more sms
     */
    public function readSMS()
    {
        $command_id = SMPP::DELIVER_SM;
        // Check the queue
        $ql = \count($this->pdu_queue);
        for ($i = 0; $i < $ql; ++$i) {
            $pdu = $this->pdu_queue[$i];
            if ($pdu->id === $command_id) {
                // remove response
                array_splice($this->pdu_queue, $i, 1);

                return $this->parseSMS($pdu);
            }
        }
        // Read pdu
        do {
            $pdu = $this->readPDU();
            if (false === $pdu) {
                return false;
            } // TSocket v. 0.6.0+ returns false on timeout
            // check for enquire link command
            if (SMPP::ENQUIRE_LINK === $pdu->id) {
                $response = new PDU(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00");
                $this->sendPDU($response);
            } elseif ($pdu->id !== $command_id) { // if this is not the correct PDU add to queue
                $this->pdu_queue[] = $pdu;
            }
        } while ($pdu && $pdu->id !== $command_id);

        if ($pdu) {
            return $this->parseSMS($pdu);
        }

        return false;
    }

    /**
     * Send one SMS to SMSC. Can be executed only after bindTransmitter() call.
     * $message is always in octets regardless of the data encoding.
     * For correct handling of Concatenated SMS, message must be encoded with GSM 03.38 (data_coding 0x00) or UCS-2BE (0x08).
     * Concatenated SMS'es uses 16-bit reference numbers, which gives 152 GSM 03.38 chars or 66 UCS-2BE chars per CSMS.
     * If we are using 8-bit ref numbers in the UDH for CSMS it's 153 GSM 03.38 chars.
     *
     * @param string $message
     * @param array  $tags                 (optional)
     * @param int    $dataCoding           (optional)
     * @param int    $priority             (optional)
     * @param string $scheduleDeliveryTime (optional)
     * @param string $validityPeriod       (optional)
     *
     * @return string message id
     */
    public function sendSMS(Address $from, Address $to, $message, $tags = null, $dataCoding = SMPP::DATA_CODING_DEFAULT, $priority = 0x00, $scheduleDeliveryTime = null, $validityPeriod = null)
    {
        $msg_length = \strlen($message);

        if ($msg_length > 160 && SMPP::DATA_CODING_UCS2 !== $dataCoding && SMPP::DATA_CODING_DEFAULT !== $dataCoding) {
            return false;
        }

        switch ($dataCoding) {
            case SMPP::DATA_CODING_UCS2:
                $singleSmsOctetLimit = 140; // in octets, 70 UCS-2 chars
                $csmsSplit = 132; // There are 133 octets available, but this would split the UCS the middle so use 132 instead
                break;
            case SMPP::DATA_CODING_DEFAULT:
                $singleSmsOctetLimit = 160; // we send data in octets, but GSM 03.38 will be packed in septets (7-bit) by SMSC.
                $csmsSplit = (self::CSMS_8BIT_UDH === self::$csms_method) ? 153 : 152; // send 152/153 chars in each SMS (SMSC will format data)
                break;
            default:
                $singleSmsOctetLimit = 254; // From SMPP standard
                break;
        }

        // Figure out if we need to do CSMS, since it will affect our PDU
        if ($msg_length > $singleSmsOctetLimit) {
            $doCsms = true;
            if (self::CSMS_PAYLOAD !== self::$csms_method) {
                $parts = $this->splitMessageString($message, $csmsSplit, $dataCoding);
                $short_message = reset($parts);
                $csmsReference = $this->getCsmsReference();
            }
        } else {
            $short_message = $message;
            $doCsms = false;
        }

        // Deal with CSMS
        if ($doCsms) {
            if (self::CSMS_PAYLOAD === self::$csms_method) {
                $payload = new Tag(Tag::MESSAGE_PAYLOAD, $message, $msg_length);

                return $this->submitSM($from, $to, null, empty($tags) ? [$payload] : array_merge($tags, [$payload]), $dataCoding, $priority, $scheduleDeliveryTime, $validityPeriod);
            } elseif (self::CSMS_8BIT_UDH === self::$csms_method) {
                $seqnum = 1;
                foreach ($parts as $part) {
                    $udh = pack('cccccc', 5, 0, 3, substr($csmsReference, 1, 1), \count($parts), $seqnum);
                    $res = $this->submitSM($from, $to, $udh.$part, $tags, $dataCoding, $priority, $scheduleDeliveryTime, $validityPeriod, self::$sms_esm_class | 0x40);
                    ++$seqnum;
                }

                return $res;
            }
            $sar_msg_ref_num = new Tag(Tag::SAR_MSG_REF_NUM, $csmsReference, 2, 'n');
            $sar_total_segments = new Tag(Tag::SAR_TOTAL_SEGMENTS, \count($parts), 1, 'c');
            $seqnum = 1;
            foreach ($parts as $part) {
                $sartags = [$sar_msg_ref_num, $sar_total_segments, new Tag(Tag::SAR_SEGMENT_SEQNUM, $seqnum, 1, 'c')];
                $res = $this->submitSM($from, $to, $part, empty($tags) ? $sartags : array_merge($tags, $sartags), $dataCoding, $priority, $scheduleDeliveryTime, $validityPeriod);
                ++$seqnum;
            }

            return $res;
        }

        return $this->submitSM($from, $to, $short_message, $tags, $dataCoding);
    }

    /**
     * Send the enquire link command.
     *
     * @return PDU
     */
    public function enquireLink()
    {
        $response = $this->sendCommand(SMPP::ENQUIRE_LINK, null);

        return $response;
    }

    /**
     * Respond to any enquire link we might have waiting.
     * If will check the queue first and respond to any enquire links we have there.
     * Then it will move on to the transport, and if the first PDU is enquire link respond,
     * otherwise add it to the queue and return.
     */
    public function respondEnquireLink()
    {
        // Check the queue first
        $ql = \count($this->pdu_queue);
        for ($i = 0; $i < $ql; ++$i) {
            $pdu = $this->pdu_queue[$i];
            if (SMPP::ENQUIRE_LINK === $pdu->id) {
                // remove response
                array_splice($this->pdu_queue, $i, 1);
                $this->sendPDU(new PDU(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00"));
            }
        }

        // Check the transport for data
        if ($this->transport->hasData()) {
            $pdu = $this->readPDU();
            if (SMPP::ENQUIRE_LINK === $pdu->id) {
                $this->sendPDU(new PDU(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00"));
            } elseif ($pdu) {
                $this->pdu_queue[] = $pdu;
            }
        }
    }

    /**
     * Makes the client executes in debug mode.
     *
     * @return void
     */
    public static function debug()
    {
        self::$debugMode = true;
    }

    /**
     * Configuration value making client to terminate string with NULL character.
     *
     * @return void
     */
    public static function setNullTerminatingString()
    {
        static::$null_terminate_octets = true;
    }

    /**
     * Perform the actual submit_sm call to send SMS.
     * Implemented as a protected method to allow automatic sms concatenation.
     * Tags must be an array of already packed and encoded TLV-params.
     *
     * @param string $short_message
     * @param array  $tags
     * @param int    $dataCoding
     * @param int    $priority
     * @param string $scheduleDeliveryTime
     * @param string $validityPeriod
     * @param string $esmClass
     *
     * @return string message id
     */
    protected function submitSM(Address $source, Address $destination, $short_message = null, $tags = null, $dataCoding = SMPP::DATA_CODING_DEFAULT, $priority = 0x00, $scheduleDeliveryTime = null, $validityPeriod = null, $esmClass = null)
    {
        if (null === $esmClass) {
            $esmClass = self::$sms_esm_class;
        }

        // Construct PDU with mandatory fields
        $pdu = pack(
            'a1cca'.(\strlen($source->value) + 1).'cca'.(\strlen($destination->value) + 1).'ccc'.($scheduleDeliveryTime ? 'a16x' : 'a1').($validityPeriod ? 'a16x' : 'a1').'ccccca'.(\strlen($short_message) + (self::$null_terminate_octets ? 1 : 0)),
            self::$sms_service_type,
            $source->typeOfNumber,
            $source->numberingPlanIndicator,
            $source->value,
            $destination->typeOfNumber,
            $destination->numberingPlanIndicator,
            $destination->value,
            $esmClass,
            self::$sms_protocol_id,
            $priority,
            $scheduleDeliveryTime,
            $validityPeriod,
            self::$sms_registered_delivery_flag,
            self::$sms_replace_if_present_flag,
            $dataCoding,
            self::$sms_sm_default_msg_id,
            \strlen($short_message), // sm_length
            $short_message // short_message
        );
        // Add any tags
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $pdu .= $tag->getBinary();
            }
        }
        $response = $this->sendCommand(SMPP::SUBMIT_SM, $pdu);
        $body = \is_string($response) ? unpack('a*msgid', $response->body) : [];

        return $body['msgid'] ?? null;
    }

    /**
     * Get a CSMS reference number for sar_msg_ref_num.
     * Initializes with a random value, and then returns the number in sequence with each call.
     */
    protected function getCsmsReference()
    {
        $limit = (self::CSMS_8BIT_UDH === self::$csms_method) ? 255 : 65535;
        if (!isset($this->sar_msg_ref_num)) {
            $this->sar_msg_ref_num = random_int(0, $limit);
        }
        ++$this->sar_msg_ref_num;
        if ($this->sar_msg_ref_num > $limit) {
            $this->sar_msg_ref_num = 0;
        }

        return $this->sar_msg_ref_num;
    }

    /**
     * Split a message into multiple parts, taking the encoding into account.
     * A character represented by an GSM 03.38 escape-sequence shall not be split in the middle.
     * Uses str_split if at all possible, and will examine all split points for escape chars if it's required.
     *
     * @param string $message
     * @param int    $split
     * @param int    $dataCoding (optional)
     */
    protected function splitMessageString($message, $split, $dataCoding = SMPP::DATA_CODING_DEFAULT)
    {
        switch ($dataCoding) {
            case SMPP::DATA_CODING_DEFAULT:
                $msg_length = \strlen($message);
                // Do we need to do php based split?
                $numParts = floor($msg_length / $split);
                if (0 === $msg_length % $split) {
                    --$numParts;
                }
                $slowSplit = false;

                for ($i = 1; $i <= $numParts; ++$i) {
                    if ("\x1B" === $message[$i * $split - 1]) {
                        $slowSplit = true;
                        break;
                    }
                }
                if (!$slowSplit) {
                    return str_split($message, $split);
                }

                // Split the message char-by-char
                $parts = [];
                $part = null;
                $n = 0;
                for ($i = 0; $i < $msg_length; ++$i) {
                    $c = $message[$i];
                    // reset on $split or if last char is a GSM 03.38 escape char
                    if ($n === $split || ($n === ($split - 1) && "\x1B" === $c)) {
                        $parts[] = $part;
                        $n = 0;
                        $part = null;
                    }
                    $part .= $c;
                }
                $parts[] = $part;

                return $parts;
            case SMPP::DATA_CODING_UCS2: // UCS2-BE can just use str_split since we send 132 octets per message, which gives a fine split using UCS2
            default:
                return str_split($message, $split);
        }
    }

    /**
     * Binds the socket and opens the session on SMSC.
     *
     * @param string $login - ESME system_id
     *
     * @return PDU
     */
    protected function _bind($login, $pass, $command_id)
    {
        // Make PDU body
        $pduBody = pack(
            'a'.(\strlen($login) + 1).
                'a'.(\strlen($pass) + 1).
                'a'.(\strlen(self::$system_type) + 1).
                'CCCa'.(\strlen(self::$address_range) + 1),
            $login,
            $pass,
            self::$system_type,
            self::$interface_version,
            self::$addr_ton,
            self::$addr_npi,
            self::$address_range
        );

        $response = $this->sendCommand($command_id, $pduBody);
        if (SMPP::ESME_ROK !== $response->status) {
            throw new TransportException(SMPP::getStatusMessage($response->status), $response->status);
        }

        return $response;
    }

    /**
     * Parse received PDU from SMSC.
     *
     * @param PDU $pdu - received PDU from SMSC
     *
     * @return parsed PDU as array
     */
    protected function parseSMS(PDU $pdu)
    {
        // Check command id
        if (SMPP::DELIVER_SM !== $pdu->id) {
            throw new \InvalidArgumentException('PDU is not an received SMS');
        }

        // Unpack PDU
        $ar = unpack('C*', $pdu->body);

        // Read mandatory params
        $service_type = $this->getString($ar, 6, true);

        $source_addr_ton = next($ar);
        $source_addr_npi = next($ar);
        $source_addr = $this->getString($ar, 21);
        $source = new Address($source_addr, $source_addr_ton, $source_addr_npi);

        $dest_addr_ton = next($ar);
        $dest_addr_npi = next($ar);
        $destination_addr = $this->getString($ar, 21);
        $destination = new Address($destination_addr, $dest_addr_ton, $dest_addr_npi);

        $esmClass = next($ar);
        $protocolId = next($ar);
        $priorityFlag = next($ar);
        next($ar); // schedule_delivery_time
        next($ar); // validity_period
        $registeredDelivery = next($ar);
        next($ar); // replace_if_present_flag
        $dataCoding = next($ar);
        next($ar); // sm_default_msg_id
        $sm_length = next($ar);
        $message = $this->getString($ar, $sm_length);

        // Check for optional params, and parse them
        if (false !== current($ar)) {
            $tags = [];
            do {
                $tag = $this->parseTag($ar);
                if (false !== $tag) {
                    $tags[] = $tag;
                }
            } while (false !== current($ar));
        } else {
            $tags = null;
        }

        if (($esmClass & SMPP::ESM_DELIVER_SMSC_RECEIPT) !== 0) {
            $sms = new DeliveryReceipt($pdu->id, $pdu->status, $pdu->sequence, $pdu->body, $service_type, $source, $destination, $esmClass, $protocolId, $priorityFlag, $registeredDelivery, $dataCoding, $message, $tags);
            $sms->parseDeliveryReceipt();
        } else {
            $sms = new SMS($pdu->id, $pdu->status, $pdu->sequence, $pdu->body, $service_type, $source, $destination, $esmClass, $protocolId, $priorityFlag, $registeredDelivery, $dataCoding, $message, $tags);
        }

        if (static::$debugMode) {
            $this->log("Received sms:\n".print_r($sms, true));
        }

        // Send response of recieving sms
        $response = new PDU(SMPP::DELIVER_SM_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00");
        $this->sendPDU($response);

        return $sms;
    }

    /**
     * Reconnect to SMSC.
     * This is mostly to deal with the situation were we run out of sequence numbers.
     */
    protected function reconnect()
    {
        $this->close();
        sleep(1);
        $this->transport->open();
        $this->sequence_number = 1;

        if ('receiver' === $this->mode) {
            $this->bindReceiver($this->login, $this->pass);
        } else {
            $this->bindTransmitter($this->login, $this->pass);
        }
    }

    /**
     * Sends the PDU command to the SMSC and waits for response.
     *
     * @param int    $id      - command ID
     * @param string $pduBody - PDU body
     *
     * @return PDU
     */
    protected function sendCommand($id, $pduBody)
    {
        if (!$this->transport->isOpen()) {
            return false;
        }
        $pdu = new PDU($id, 0, $this->sequence_number, $pduBody);
        $this->sendPDU($pdu);
        $response = $this->readPDUResponse($this->sequence_number, $pdu->id);
        if (false === $response) {
            throw new TransportException('Failed to read reply to command: 0x'.dechex($id));
        }

        if (SMPP::ESME_ROK !== $response->status) {
            throw new TransportException(SMPP::getStatusMessage($response->status), $response->status);
        }

        ++$this->sequence_number;

        // Reached max sequence number, spec does not state what happens now, so we re-connect
        if ($this->sequence_number >= 0x7FFFFFFF) {
            $this->reconnect();
        }

        return $response;
    }

    /**
     * Prepares and sends PDU to SMSC.
     */
    protected function sendPDU(PDU $pdu)
    {
        $length = \strlen($pdu->body) + 16;
        $header = pack('NNNN', $length, $pdu->id, $pdu->status, $pdu->sequence);
        if (static::$debugMode) {
            $this->log("Send PDU         : $length bytes");
            $this->log(' '.chunk_split(bin2hex($header.$pdu->body), 2, ' '));
            $this->log(' command_id      : 0x'.dechex($pdu->id));
            $this->log(' sequence number : '.$pdu->sequence);
        }
        $this->transport->write($header.$pdu->body, $length);
    }

    /**
     * Waits for SMSC response on specific PDU.
     * If a GENERIC_NACK with a matching sequence number, or null sequence is received instead it's also accepted.
     * Some SMPP servers, ie. logica returns GENERIC_NACK on errors.
     *
     * @param int $seq_number - PDU sequence number
     * @param int $command_id - PDU command ID
     *
     * @throws TransportException
     *
     * @return PDU
     */
    protected function readPDUResponse($seq_number, $command_id)
    {
        // Get response cmd id from command id
        $command_id = $command_id | SMPP::GENERIC_NACK;

        // Check the queue first
        $ql = \count($this->pdu_queue);
        for ($i = 0; $i < $ql; ++$i) {
            $pdu = $this->pdu_queue[$i];
            if (
                ($pdu->sequence === $seq_number && ($pdu->id === $command_id || SMPP::GENERIC_NACK === $pdu->id))
                || (null === $pdu->sequence && SMPP::GENERIC_NACK === $pdu->id)
            ) {
                // remove response pdu from queue
                array_splice($this->pdu_queue, $i, 1);

                return $pdu;
            }
        }

        // Read PDUs until the one we are looking for shows up, or a generic nack pdu with matching sequence or null sequence
        do {
            $pdu = $this->readPDU();
            if ($pdu) {
                if ($pdu->sequence === $seq_number && ($pdu->id === $command_id || SMPP::GENERIC_NACK === $pdu->id)) {
                    return $pdu;
                }
                if (null === $pdu->sequence && SMPP::GENERIC_NACK === $pdu->id) {
                    return $pdu;
                }
                $this->pdu_queue[] = $pdu; // unknown PDU push to queue
            }
        } while ($pdu);

        return false;
    }

    /**
     * Reads incoming PDU from SMSC.
     *
     * @return PDU
     */
    protected function readPDU()
    {
        // Read PDU length
        $bufLength = $this->transport->read(4);
        if (!$bufLength) {
            return false;
        }
        extract(unpack('Nlength', $bufLength));

        // Read PDU headers
        $bufHeaders = $this->transport->read(12);
        if (!$bufHeaders) {
            return false;
        }
        extract(unpack('Ncommand_id/Ncommand_status/Nsequence_number', $bufHeaders));
        // Read PDU body
        if ($length - 16 > 0) {
            $body = $this->transport->readAll($length - 16);
            if (!$body) {
                throw new \RuntimeException('Could not read PDU body');
            }
        } else {
            $body = null;
        }

        if (static::$debugMode) {
            $this->log("Read PDU         : $length bytes");
            $this->log(' '.chunk_split(bin2hex($bufLength.$bufHeaders.$body), 2, ' '));
            $this->log(' command id      : 0x'.dechex($command_id));
            $this->log(' command status  : 0x'.dechex($command_status).' '.SMPP::getStatusMessage($command_status));
            $this->log(' sequence number : '.$sequence_number);
        }

        return new PDU($command_id, $command_status, $sequence_number, $body);
    }

    /**
     * Reads C style null padded string from the char array.
     * Reads until $maxlen or null byte.
     *
     * @param array $ar        - input array
     * @param int   $maxlen    - maximum length to read
     * @param bool  $firstRead - is this the first bytes read from array?
     *
     * @return read string
     */
    protected function getString(&$ar, $maxlen = 255, $firstRead = false)
    {
        $string = '';
        $i = 0;
        do {
            $octect = ($firstRead && 0 === $i) ? current($ar) : next($ar);
            if (0 !== $octect) {
                $string .= \chr($octect);
            }
            ++$i;
        } while ($i < $maxlen && 0 !== $octect);

        return $string;
    }

    /**
     * Read a specific number of octets from the char array.
     * Does not stop at null byte.
     *
     * @param array  $octects - input array
     * @param intger $length
     */
    protected function getOctets(&$octects, $length)
    {
        $string = '';
        for ($i = 0; $i < $length; ++$i) {
            $octect = next($octects);
            if (false === $octect) {
                return $string;
            }
            $string .= \chr($octect);
        }

        return $string;
    }

    protected function parseTag(&$ar)
    {
        $unpackedData = unpack('nid/nlength', pack('C2C2', next($ar), next($ar), next($ar), next($ar)));
        if (!$unpackedData) {
            throw new \InvalidArgumentException('Could not read tag data');
        }
        extract($unpackedData);

        // Sometimes SMSC return an extra null byte at the end
        if (0 === $length && 0 === $id) {
            return false;
        }

        $value = $this->getOctets($ar, $length);
        $tag = new Tag($id, $value, $length);
        if (static::$debugMode) {
            $this->log('Parsed tag:');
            $this->log(' id     :0x'.dechex($tag->id));
            $this->log(' length :'.$tag->length);
            $this->log(' value  :'.chunk_split(bin2hex($tag->value), 2, ' '));
        }

        return $tag;
    }

    /**
     * Log debug message using the debug logger.
     *
     * @return void
     */
    protected function log(string $message)
    {
        if ($this->debugLogger) {
            \call_user_func($this->debugLogger, $message);
        }
    }
}
