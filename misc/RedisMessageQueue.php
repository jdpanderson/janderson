<?php

namespace janderson\misc;

use \Redis;
use janderson\misc\UUID;

require 'UUID.php';

/**
 * Use Redis data structures to provide a message queue system.
 *
 * Message queues are created and identified by UUIDs.
 * Subscribers are also identified by unique UUIDs.
 *
 * Each message queue has a set of subscribers:
 *  - {msgq:<queue uuid>:subscribers}
 *
 * The messages sent to the queue are stored in a list:
 *  - {msgq:<queue uuid>}
 *
 * Each subscriber to a queue has an index of the first unread message:
 *  - {msgq:subscriber:<subscriber uuid>:index:<queue uuid>}
 *
 * Each subscriber can send a message, which appends an element to the list.
 * The list can be trimmed depending on the last read message index of the subscribers. When this happens, all subscriber indexes have to be updated.
 */
class RedisMessageQueue
{
	private $queue;
	private $subscriber;
	private $redis;

	private $qkey;
	private $idxkey;
	private $subkey;

	public function __construct(Redis $redis, $queue = NULL, $subscriber = NULL)
	{
		$this->redis = $redis;

		$this->queue = empty($queue) ? UUID::v4() : $queue;
		$this->subscriber = empty($subscriber) ? UUID::v4() : $subscriber;

		$this->qkey = sprintf('{msgq:%s}', $this->queue);
		$this->subkey = sprintf('{msgq:%s:subscribers}', $this->queue);
		$this->idxkey = sprintf('{msgq:subscriber:%s:index:%s}', $this->subscriber, $this->queue);

		$this->subscribe();
	}

	private function subscribe()
	{
		$length = $this->redis->get($this->qkey);
		$this->redis->sadd($this->subkey, $this->subscriber);
		$this->redis->set($this->idxkey, $length);
	}

	private function unsubscribe()
	{
		$this->redis->srem($this->subkey, $this->subscriber);
		$this->redis->del($this->idxkey);
	}

	/**
	 * Sends on message to the queue.
	 */
	public function send($message)
	{
		$this->redis->rpush($this->qkey, $message);
	}

	/**
	 * Reads one message from the queue.
	 *
	 * @return string Returns the next message, or false if there are no messages to read.
	 */
	public function read()
	{
		$length = $this->redis->llen($this->qkey);
		$index = (int)$this->redis->get($this->idxkey);

		if ($index >= $length) {
			return FALSE;
		}

		$value = $this->redis->lindex($this->qkey, $index);
		$this->redis->incr($this->idxkey);

		return $value;
	}
}

$redis = new Redis();
$redis->connect('127.0.0.1');

$q = new RedisMessageQueue($redis);
var_dump($q);
$q->send('test1');
$q->send('test2');
var_dump($q->read());
var_dump($q->read());
var_dump($q->read());
$q->send('test3');
var_dump($q->read());