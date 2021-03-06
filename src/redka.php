<?php
/**
 * Redka v0.1
 * Created by Sergey Peshalov https://github.com/desfpc
 * Lite redis php class
 * https://github.com/desfpc/Redka
 *
 * Redis PHP Class
 *
 */

namespace redka;

class redkaConnection
{

    public $debug = false;
    public $status = 0;
    protected $socket;
    private $lang;
    private $texts = [
        'error' => [
            'en' => 'Service is temporarily unavailable',
            'ru' => 'Сервис временно не работает'
        ],
        'debugConnectionError' => [
            'en' => 'Failed to open connect to Redis',
            'ru' => 'Генерал Файлура не открыл соединение с Redis'
        ]
    ];

    public function __construct($host = '127.0.0.1', $port = 6379, $lang = 'en', $debug = false)
    {

        $this->lang = $lang;
        $this->debug = $debug;

        if ($host == 'localhost') {
            $host = '127.0.0.1';;
        }

        try {
            $socket = fsockopen($host, $port, $errno, $errstr);
        } catch (\Throwable $e) {
            if ($this->debug) {
                die($this->texts['debugConnectionError'][$this->lang] . ': ' . $host . ':' . $port . ' - (' . $errno . ') ' . $errstr);
            }
            //die($this->texts['error'][$this->lang]);
            return false;
        }

        if (!is_resource($socket)) {

            if ($this->debug) {
                die($this->texts['debugConnectionError'][$this->lang] . ': ' . $host . ':' . $port . ' - (' . $errno . ') ' . $errstr);
            }

            return false;
        }

        if (!$socket) {
            return false;
        }

        $this->socket = $socket;
        $this->status = 1;
        return true;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function send($command)
    {
        return fwrite($this->socket, $command);
    }

    public function read()
    {
        return fgets($this->socket);
    }

    public function positionRead($position)
    {
        return fread($this->socket, $position);
    }
}

class redka
{
    const LIST_PUSH_RIGHT = 10;
    const LIST_POP_RIGHT = 10;

    const LIST_PUSH_LEFT = 20;
    const LIST_POP_LEFT = 20;
    public $debug = false;
    public $langs = ['en', 'ru'];
    public $status = 0;
    /**
     * @var redkaConnection
     */
    protected $connection;
    protected $host = 'localhost';
    protected $port = 6379;
    private $lang;
    private $texts = [
        'keyNotFound' => [
            'en' => 'Can\'t found in Redis key ',
            'ru' => 'Не могу найтить в Redis-е ключ '
        ],
        'unableToReadReply' => [
            'en' => 'Unable to read reply from Redis for command',
            'ru' => 'Невозможно прочитать ответ Redis-а для команды'
        ],
        'error' => [
            'en' => 'Redis error',
            'ru' => 'Ошибка Redis'
        ],
        'wtf' => [
            'en' => 'Non-protocol answer',
            'ru' => 'Неведомый ответ не по протоколу'
        ]
    ];

    public function __construct($host = 'localhost', $port = 6379, $lang = 'en', $debug = false)
    {
        $this->host = $host;
        $this->port = $port;

        if (!in_array($lang, $this->langs)) {
            $lang = 'en';
        }

        $this->lang = $lang;
        $this->debug = $debug;

    }

    public function get($key)
    {
        if (!$this->has($key)) {
            die($this->texts['keyNotFound'][$this->lang]);
        }

        return $this->send('get', array($key));
    }

    public function has($key)
    {
        return (boolean)$this->send('exists', array($key));
    }

    public function send($command, array $arguments = array())
    {
        return $this->execute(array_merge(array($command), $arguments));
    }

    protected function execute(array $arguments)
    {

        if (!$this->connection) {
            $this->connect();
        }

        $command = '*' . count($arguments) . "\r\n";

        foreach ($arguments as $argument) {
            $command .= '$' . strlen($argument) . "\r\n" . $argument . "\r\n";
        }

        if (!$this->connection->send($command)) {

            $this->connect();

            if (!$this->connection->send($command)) {
                return false;
            }
        }

        return $this->readReply($command);
    }

    public function connect()
    {
        $this->connection = new redkaConnection($this->host, $this->port, $this->lang, $this->debug);

        if ($this->connection->status == 1) {
            $this->status = 1;
        }

        return $this;
    }

    protected function readReply($command)
    {
        $reply = $this->connection->read();

        if ($reply === false) {
            $this->connect();
            $reply = $this->connection->read();

            if ($reply === false) {
                die($this->texts['unableToReadReply'][$this->lang] . ' ' . $command);
            }
        }

        $reply = trim($reply);

        switch ($reply[0]) {
            case '-':
                die($this->texts['error'][$this->lang] . ' ' . $reply);
                break;
            case '+':
                return substr($reply, 1);
                break;
            case '$':
                $response = null;

                if ($reply == '$-1') {
                    return false;
                    break;
                }

                $size = intval(substr($reply, 1));

                if ($size > 0) {
                    $response = stream_get_contents($this->connection->getSocket(), $size);
                }

                // Discard crlf
                $this->connection->positionRead(2);
                return $response;
                break;
            case '*':
                $count = substr($reply, 1);

                if ($count == '-1') {
                    return null;
                }

                $response = array();

                for ($i = 0; $i < $count; $i++) {
                    $response[] = $this->readReply($command);
                }

                return $response;
                break;
            case ':':
                return intval(substr($reply, 1));
                break;
            default:
                echo $this->texts['error'][$this->lang] . ' ' . $this->texts['wtf'][$this->lang] . ': ';
                print_r($reply);
                die();
                break;
        }
    }

    public function del($key)
    {
        return $this->send('del', array($key));
    }

    public function set($key, $value, $expire = null)
    {
        if (is_int($expire)) {
            return $this->send('setex', array($key, $expire, $value));
        } else {
            return $this->send('set', array($key, $value));
        }
    }

    public function listPush($listName, $value, $pushType = self::LIST_PUSH_RIGHT)
    {
        $command = 'rpush';

        if ($pushType == self::LIST_PUSH_LEFT) {
            $command = 'lpush';
        }

        return $this->send($command, array($listName, $value));
    }

    public function listPop($listName, $popType = self::LIST_POP_RIGHT)
    {
        $command = 'rpop';

        if ($popType == self::LIST_POP_LEFT) {
            $command = 'lpop';
        }

        return $this->send($command, array($listName));
    }

    public function listGet($listName, $index)
    {
        return $this->send('lindex', array($listName, $index));
    }

    public function hashGet($hashName, $key)
    {
        return $this->send('hget', array($hashName, $key));
    }

    public function hashSet($hashName, $key, $value)
    {
        return (boolean)$this->send('hset', array($hashName, $key, $value));
    }

    public function hashDelete($hashName, $key)
    {
        return (boolean)$this->send('hdel', array($hashName, $key));
    }

    public function listSet($listName, $index, $value)
    {
        return $this->send('lset', array($listName, $index, $value));
    }

    public function listGetRange($listName, $firstIndex, $lastIndex)
    {
        return $this->send('lrange', array($listName, $firstIndex, $lastIndex));
    }

    public function listLength($listName)
    {
        return $this->send('llen', array($listName));
    }

    public function remove($key)
    {
        return $this->send('del', array($key));
    }

    public function authenticate($password)
    {
        return $this->send('auth', array($password));
    }

    public function persist($key)
    {
        return $this->send('persist', array($key));
    }

    public function findKeys($pattern = '*')
    {
        return $this->send('keys', array($pattern));
    }

    public function flush()
    {
        return $this->send('flushdb');
    }

    public function getStats()
    {
        return $this->send('info');
    }

    public function getParameter($parameterName)
    {
        return $this->send('config', array('GET', $parameterName));
    }

    public function setParameter($parameterName, $value)
    {
        return $this->send('config', array('SET', $parameterName, $value));
    }

    public function getSize()
    {
        return $this->send('dbsize');
    }
}