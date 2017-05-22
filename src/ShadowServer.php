<?php
namespace SS;
require_once __DIR__ . '/Util/helpers.php';

use Swoole\Buffer;
use Swoole\Client;
use Swoole\Server;

class ShadowServer
{
    const ADDRTYPE_IPV4 = 0x01;
    const ADDRTYPE_HOST = 0x03;
    const ADDRTYPE_IPV6 = 0x04;

    /** @var Backend[] */
    private $backends = [];

    public function start()
    {
        $config = include_once __DIR__ . '/../config/server.php';
        $server = new Server($config['host'], $config['port'], SWOOLE_BASE);

        $setting = [
            'max_conn'           => 500,
            'daemonize'          => false,
            'reactor_num'        => 1,
            'worker_num'         => 1,
            'dispatch_mode'      => 2,
            'buffer_output_size' => 2 * 1024 * 1024,
            'open_cpu_affinity'  => true,
            'open_tcp_nodelay'   => true,
            'log_file'           => "log/" . $config['log_file'],
        ];
        $server->set($setting);
        // todo too slow
        Encryptor::init();

        $server->on('connect', [$this, 'onConnect']);
        $server->on('receive', [$this, 'onReceive']);
        $server->on('close', [$this, 'onClose']);

        sys_echo("listen: tcp://" . $config['host'] . ":" . $config['port']);
        $server->start();
    }

    public function onConnect(Server $server, $fd, $fromId)
    {
        sys_echo("connecting ......");
        $backend              = new Backend();
        $backend->isConnected = true;
        $backend->status      = Backend::STATUS_BIND;
        $backend->full        = new Buffer();
        $this->backends[$fd]   = $backend;
    }

    public function onReceive(Server $server, $fd, $fromId, $data)
    {
        $backend = $this->backends[$fd];
        // 先解密数据
        $data = Encryptor::decrypt($data);
        $buffer  = new Buffer();
        $buffer->append($data);
        sys_echo("backend status:" . $backend->status);
        if (Backend::STATUS_BIND == $backend->status) {
            // 解析socket5头
            $headerData = $this->parseSocket5Header($buffer);
            // 解析头部出错，则关闭连接
            if(!$headerData)
            {
                $server->close($fd);
                return;
            }
            // 头部长度
            $headerLen = $headerData['header_length'];
            // 解析得到实际请求地址及端口
            $addr = $headerData['dest_addr'];
            $port = $headerData['dest_port'];
            if (!$addr || !$port) {
                $server->close($fd);
                return;
            }

            $remote = new Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

            $remote->on('connect', function (Client $cli) use ($backend, $server, $fd, $remote) {
                sys_echo('change backend to connect');
                $backend->status = Backend::STATUS_CONNECT;
                $backend->remote = $remote;
            });
            $remote->on('error', function (Client $cli) use ($server, $fd) {
                sys_echo("Error: " . $cli->errCode);
                $server->close($fd);
            });
            $remote->on('close', function (Client $cli) use ($server, $fd, $backend) {
                $backend->remote = null;
                $server->close($fd);
            });
            $remote->on('receive', function (Client $cli, $data) use ($server, $fd, $backend) {
                if ($backend->isConnected) {
                    $server->send($fd, $data);
                }
            });
            if (self::ADDRTYPE_HOST == $headerData['addr_type']) {
                swoole_async_dns_lookup($addr, function ($host, $ip) use ($remote, $port, $backend, $buffer) {
                    sys_echo('connecting: ' . $ip . ":" . $port);
                    $remote->connect($ip, $port);
                    if ($backend->remote === null) {
                        sys_echo("remote connection has been closed.");
                        return;
                    }

                    $sendByteCount = $backend->request();
                    sys_echo("data length:" . $backend->full->length . ' send byte count:' . $sendByteCount);
                });
            } else {
                sys_echo('connecting: ' . $addr . ":" . $port);
                $remote->connect($addr, $port);
            }
            $buffer->clear();
        }
    }

    /**
     * @param Buffer $buffer
     */
    private function parseSocket5Header($buffer)
    {
        $addrType = ord($buffer->substr(0, 1));
        switch ($addrType)
        {
            case self::ADDRTYPE_IPV4:
                $destAddr = ord($buffer->substr(1, 1))
                    .'.'.ord($buffer->substr(2, 1))
                    .'.'.ord($buffer->substr(3, 1))
                    .'.'.ord($buffer->substr(4, 1));
                $portData = unpack('n', substr($buffer->substr(5 ,2)));
                $destPort = $portData[1];
                $headerLength = 7;
                break;
            case self::ADDRTYPE_HOST:
                $addrlen = ord($buffer->substr(1, 1));
                $destAddr = $buffer->substr(2, $addrlen);
                $portData = unpack('n', $buffer->substr(2 + $addrlen, 2));
                $destPort = $portData[1];
                $headerLength = $addrlen + 4;
                break;
            case self::ADDRTYPE_IPV6:
                echo "todo ipv6 not support yet\n";
                return false;
            default:
                echo "unsupported addrtype $addrType\n";
                return false;
        }
        return [
            'addr_type' => $addrType,
            'dest_addr' => $destAddr,
            'dest_port' => $destPort,
            'header_length' => $headerLength
        ];
    }

    public function onClose(Server $server, $fd, $fromId)
    {
        sys_echo("closing .....");

        $client = $this->backends[$fd];
        if ($client->remote) $client->remote->close();
        $client->isConnected = false;
    }
}