<?php
declare(strict_types=1);
/**
 * Redka v1.00
 * Created by Sergey Peshalov https://github.com/desfpc
 * Lite redis php class
 * https://github.com/desfpc/Redka
 *
 * Redis PHP Class
 */

namespace desfpc\Redka;

use Exception;

class Redka
{
    const LIST_PUSH_RIGHT = 10;
    const LIST_POP_RIGHT = 10;
    const LIST_PUSH_LEFT = 20;
    const LIST_POP_LEFT = 20;

    public bool $debug = false;
    public array $langs = ['en', 'ru'];
    public int $status = 0;
    protected Connection $connection;
    protected string $host = 'localhost';
    protected int $port = 6379;
    private string $lang;
    private array $texts = [
        'keyNotFound' => [
            'en' => 'Can\'t found in Redis key ',
            'ru' => 'Не могу найти в Redis-е ключ '
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

    /**
     * Constructor class
     *
     * @param string $host
     * @param int $port
     * @param string $lang
     * @param bool $debug
     */
    public function __construct(string $host = 'localhost', int $port = 6379, string $lang = 'en', bool $debug = false)
    {
        $this->host = $host;
        $this->port = $port;

        if (!in_array($lang, $this->langs)) {
            $lang = 'en';
        }

        $this->lang = $lang;
        $this->debug = $debug;
    }

    /**
     * Getting value by key
     *
     * @param $key
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function get($key)
    {
        if (!$this->has($key)) {
            if ($this->debug) {
                die($this->texts['keyNotFound'][$this->lang]);
            }
            throw new Exception($this->texts['keyNotFound'][$this->lang]);
        }

        return $this->send('get', array($key));
    }

    /**
     * Checking if the key is in the cache
     *
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function has(string $key): bool
    {
        return (boolean)$this->send('exists', array($key));
    }

    /**
     * Sending command and arguments to Redis
     *
     * @param string $command
     * @param array $arguments
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function send(string $command, array $arguments = array())
    {
        return $this->execute(array_merge(array($command), $arguments));
    }

    /**
     * Executing command with arguments in Redis
     *
     * @param array $arguments
     * @return array|false|int|string|void|null
     * @throws Exception
     */
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

    /**
     * Connecting to Redis
     *
     * @return $this
     * @throws Exception
     */
    public function connect(): Redka
    {
        $this->connection = new Connection($this->host, $this->port, $this->lang, $this->debug);

        if ($this->connection->status === 1) {
            $this->status = 1;
        }

        return $this;
    }

    /**
     * Read Redis Reply
     *
     * @param string $command
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    protected function readReply(string $command)
    {
        $reply = $this->connection->read();

        if ($reply === false) {
            $this->connect();
            $reply = $this->connection->read();

            if ($reply === false) {
                if ($this->debug) {
                    die($this->texts['unableToReadReply'][$this->lang] . ' ' . $command);
                }
                throw new Exception($this->texts['unableToReadReply'][$this->lang] . ' ' . $command);
            }
        }

        $reply = trim($reply);

        switch ($reply[0]) {
            case '-':
                if ($this->debug) {
                    die($this->texts['error'][$this->lang] . ' ' . $reply);
                }
                throw new Exception($this->texts['error'][$this->lang] . ' ' . $reply);
            case '+':
                return substr($reply, 1);
            case '$':
                $response = null;

                if ($reply == '$-1') {
                    return false;
                }

                $size = intval(substr($reply, 1));

                if ($size > 0) {
                    $response = stream_get_contents($this->connection->getSocket(), $size);
                }

                // Discard crlf
                $this->connection->positionRead(2);
                return $response;
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
            case ':':
                return intval(substr($reply, 1));
            default:
                if ($this->debug) {
                    die($this->texts['error'][$this->lang] . ' ' . $this->texts['wtf'][$this->lang] . ': ' . $reply);
                }
                throw new Exception($this->texts['error'][$this->lang] . ' ' . $this->texts['wtf'][$this->lang] . ': ' . $reply);
        }
    }

    /**
     * Deleting value by key
     *
     * @param $key
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function del($key)
    {
        return $this->send('del', array($key));
    }

    /**
     * Setting value
     *
     * @param string $key
     * @param mixed $value
     * @param null|int $expire
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function set(string $key, $value, ?int $expire = null)
    {
        if (is_int($expire)) {
            return $this->send('setex', array($key, (string)$expire, $value));
        } else {
            return $this->send('set', array($key, $value));
        }
    }

    /**
     * rpush & lpush commands
     *
     * @param string $listName
     * @param mixed $value
     * @param int $pushType
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function listPush(string $listName, $value, int $pushType = self::LIST_PUSH_RIGHT)
    {
        $command = 'rpush';

        if ($pushType == self::LIST_PUSH_LEFT) {
            $command = 'lpush';
        }

        return $this->send($command, array($listName, $value));
    }

    /**
     * rpop & lpop commands
     *
     * @param string $listName
     * @param int $popType
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function listPop(string $listName, int $popType = self::LIST_POP_RIGHT)
    {
        $command = 'rpop';

        if ($popType == self::LIST_POP_LEFT) {
            $command = 'lpop';
        }

        return $this->send($command, array($listName));
    }

    /**
     * listGet (lindex command)
     *
     * @param string $listName
     * @param int $index
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function listGet(string $listName, int $index)
    {
        return $this->send('lindex', array($listName, $index));
    }

    /**
     * hashGet (hget) command
     *
     * @param string $hashName
     * @param string $key
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function hashGet(string $hashName, string $key)
    {
        return $this->send('hget', array($hashName, $key));
    }

    /**
     * hashSet (hset) command
     *
     * @param string $hashName
     * @param string $key
     * @param mixed $value
     * @return bool
     * @throws Exception
     */
    public function hashSet(string $hashName, string $key, $value): bool
    {
        return (boolean)$this->send('hset', array($hashName, $key, $value));
    }

    /**
     * hashDelete (hdel) command
     *
     * @param string $hashName
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function hashDelete(string $hashName, string $key): bool
    {
        return (boolean)$this->send('hdel', array($hashName, $key));
    }

    /**
     * listSet (lset) command
     *
     * @param string $listName
     * @param int $index
     * @param mixed $value
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function listSet(string $listName, int $index, $value)
    {
        return $this->send('lset', array($listName, $index, $value));
    }

    /**
     * listGetRange (lrange) command
     *
     * @param string $listName
     * @param int $firstIndex
     * @param int $lastIndex
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function listGetRange(string $listName, int $firstIndex, int $lastIndex)
    {
        return $this->send('lrange', array($listName, $firstIndex, $lastIndex));
    }

    /**
     * listLength (llen) command
     *
     * @param string $listName
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function listLength(string $listName)
    {
        return $this->send('llen', array($listName));
    }

    /**
     * Remove (del) command
     *
     * @param string $key
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function remove(string $key)
    {
        return $this->send('del', array($key));
    }

    /**
     * Authenticate (auth) command
     *
     * @param string $password
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function authenticate(string $password)
    {
        return $this->send('auth', array($password));
    }

    /**
     * Persist command
     *
     * @param string $key
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function persist(string $key)
    {
        return $this->send('persist', array($key));
    }

    /**
     * FindKeys (keys) command
     *
     * @param string $pattern
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function findKeys(string $pattern = '*')
    {
        return $this->send('keys', array($pattern));
    }

    /**
     * Flush DB (flushdb) command
     *
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function flush()
    {
        return $this->send('flushdb');
    }

    /**
     * GetStats (info) command
     *
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function getStats()
    {
        return $this->send('info');
    }

    /**
     * GetParameter (config GET) command
     *
     * @param string $parameterName
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function getParameter(string $parameterName)
    {
        return $this->send('config', array('GET', $parameterName));
    }

    /**
     * SetParameter (config SET) command
     *
     * @param string $parameterName
     * @param mixed $value
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function setParameter(string $parameterName, $value)
    {
        return $this->send('config', array('SET', $parameterName, $value));
    }

    /**
     * Get DB Size (dbsize) command
     *
     * @return array|false|int|string|void|null
     * @throws Exception
     */
    public function getSize()
    {
        return $this->send('dbsize');
    }
}