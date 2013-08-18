<?php

namespace janderson\misc;

use \Redis;
use janderson\misc\UUID;

require 'UUID.php';

/**
 * Use Redis data structures to provide chat rooms.
 *
 * Chat rooms are created and identified by UUIDs.
 * Users are also identified by unique UUIDs.
 *
 * Each chat room has a set of users:
 *  - {chat:<room uuid>:users}
 *
 * The if logging/history is desired messages sent to the chat room are stored in a list:
 *  - {chat:<room uuid>}
 *
 * Each user has a list of unread messages from the chat room:
 *  - {chat:<room uuid>:user:<user uuid>}
 *
 * Each user can send a message, which appends an element to the lists of each user.
 * The list can be trimmed depending on the last read message index of the users. When this happens, all user indexes have to be updated.
 */
class RedisChat
{
	private $redis;

	private $room;
	private $user;

	private $roomkey;
	private $userkey;
	private $userskey;

	private $history = FALSE;

	public function __construct(Redis $redis, $room = NULL, $user = NULL)
	{
		$this->redis = $redis;

		$this->room = empty($room) ? UUID::v4() : $room;
		$this->user = empty($user) ? UUID::v4() : $user;

		$this->roomkey = sprintf('{chat:%s}', $this->room);
		$this->userkey = sprintf('{chat:%s:user:%s}', $this->room, $this->user);
		$this->userskey = sprintf('{chat:%s:users}', $this->room);
		
		$this->join();
	}

	private function join()
	{
		/* Add this user to the set of users in the room */
		$this->redis->sadd($this->userskey, $this->user);
	}

	private function leave()
	{
		/* Remove this user from the set of users in the room */
		$this->redis->srem($this->userskey, $this->user);

		/* Remove the chat message queue for this user */
		$this->redis->del($this->userkey);
	}

	/**
	 * Sends a message to the room.
	 */
	public function send($message)
	{
		/* Get the current list of users in the room */
		$users = $this->redis->smembers($this->userskey);

		/* Put the message in the queue for each user in the room */
		foreach ($users as $user) {
			$userkey = sprintf('{chat:%s:user:%s}', $this->room, $user);
			$this->redis->rpush($userkey, $message);
		}

		/* If we're keeping history, store it in there. */
		if ($this->history) {
			$this->redis->rpush($this->roomkey, $message);
		}
	}

	/**
	 * Reads one message from the room.
	 *
	 * @return string Returns the next message, or false if there are no messages to read.
	 */
	public function read()
	{
		return $this->redis->lpop($this->userkey);
	}
}

/*
$redis = new Redis();
$redis->connect('127.0.0.1');

$q = new RedisChat($redis);
var_dump($q);
$q->send('test1');
$q->send('test2');
var_dump($q->read());
var_dump($q->read());
var_dump($q->read());
$q->send('test3');
var_dump($q->read());
*/