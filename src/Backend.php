<?php

namespace SS;

use Swoole\Buffer;
use Swoole\Client;

/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/7/18
 * Time: 15:03
 * Email: jhasheng@hotmail.com
 */
class Backend
{
    const STATUS_INIT = 'init';

    const STATUS_BIND = 'bind';

    const STATUS_AUTH = 'auth';

    const STATUS_CONNECT = 'connect';

    const STATUS_COMPLETE = 'complete';

    const CMD_CONNECT = 0x01;

    const CMD_BIND = 0x02;

    const CMD_UDP = 0x03;

    public $isConnected = true;

    public $data = null;

    /**
     * @var \Swoole\Client
     */
    public $remote = null;

    public $status;

    public $error;

    public $sendComplete = false;

    public $atyp;
    
    public $startTime;
    
    public $endTime;

    /**
     * @var Buffer
     */
    public $full;

    public function request()
    {
        if (!$this->remote instanceof Client) {
            $this->error = "client not exist";
            return false;
        }

        if (strlen($this->full->length) < 1) {
            $this->error = "data haven't value yet!";
            return false;
        }

        $result = $this->remote->send($this->full->substr(0));
        if (false === $result) {
            $this->error = $this->remote->errCode;
            return false;
        } else {
            $this->sendComplete = true;
            $this->full->clear();
            return $result;
        }
    }

    public function append($data)
    {
        if ($this->full instanceof Buffer) {
            $this->full->append($data);
        } else {
            $this->full = new Buffer();
        }
        return $this;
    }
}