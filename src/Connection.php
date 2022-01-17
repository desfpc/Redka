<?php
declare(strict_types=1);
/**
 * Redka v1.00
 * Created by Sergey Peshalov https://github.com/desfpc
 * Lite redis php class
 * https://github.com/desfpc/Redka
 *
 * Redis Connection PHP Class
 */

namespace desfpc\Redka;

use Exception;

class Connection
{
    public bool $debug = false;
    public int $status = 0;
    protected $socket;
    private string $lang;
    private array $texts = [
        'error' => [
            'en' => 'Service is temporarily unavailable',
            'ru' => 'Сервис временно не работает'
        ],
        'debugConnectionError' => [
            'en' => 'Failed to open connect to Redis',
            'ru' => 'Генерал Файлура не открыл соединение с Redis'
        ]
    ];

    /**
     * Constructor class
     *
     * @param string $host
     * @param int $port
     * @param string $lang
     * @param false $debug
     * @throws Exception
     */
    public function __construct(string $host = '127.0.0.1', int $port = 6379, string $lang = 'en', bool $debug = false)
    {
        $this->lang = $lang;
        $this->debug = $debug;

        if ($host == 'localhost') {
            $host = '127.0.0.1';;
        }

        $socket = false;

        try {
            $socket = fsockopen($host, $port, $errno, $errstr);
        } catch (\Throwable $e) {
            $this->_throwError($host, $port, $errno, $errstr);
        }

        if (!is_resource($socket)) {
            $this->_throwError($host, $port, $errno, $errstr);
        }

        $this->socket = $socket;
        $this->status = 1;
    }

    /**
     * Socket getter
     *
     * @return false|mixed|resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Send command to socket
     *
     * @param string $command
     * @return false|int
     */
    public function send(string $command)
    {
        return fwrite($this->socket, $command);
    }

    /**
     * Read from socket
     *
     * @return false|string
     */
    public function read()
    {
        return fgets($this->socket);
    }

    /**
     * Read from socket to position
     *
     * @param int $position
     * @return false|string
     */
    public function positionRead(int $position)
    {
        return fread($this->socket, $position);
    }

    /**
     * Throw Error method
     *
     * @param string $host
     * @param int $port
     * @param $errno
     * @param $errstr
     * @throws Exception
     */
    private function _throwError(string $host, int $port, $errno, $errstr): void
    {
        if ($this->debug) {
            die($this->texts['debugConnectionError'][$this->lang] . ': ' . $host . ':' . $port . ' - (' . $errno . ') ' . $errstr);
        }
        throw new Exception($this->texts['debugConnectionError'][$this->lang] . ': ' . $host . ':' . $port . ' - (' . $errno . ') ' . $errstr);
    }
}