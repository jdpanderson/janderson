<?php

namespace janderson\misc;

use \Redis;
use janderson\misc\UUID;

/**
 * Use Redis data structures to provide chat rooms.
 *
 * Chat rooms are created and identified by UUIDs, or other unique identifiers.
 * Users are identified by unique UUIDs.
 *
 * A set of active chat rooms is contained in a set:
 *  - {chat:rooms}
 *
 * Information about a chat room can be stored in a key:
 *  - {chat:<room uuid>} 
 *
 * Each chat room has a set of users:
 *  - {chat:<room uuid>:users}
 *
 * Messages sent to the chat room are stored in a list:
 *  - {chat:<room uuid>:messages}
 *
 * User metadata can be stored in a structure:
 *  - {chat:user:<user uuid>}
 *
 * Set of rooms the user is using:
 *  - {chat:user:<user uuid>:rooms}
 *
 * A user may join several chat rooms.
 *
 * When joining a room:
 *  - The user requests how many messages of history they want.
 *
 * They are returned:
 *  - Any requested history
 *  - Any chat room metadata
 *  - The index of the last sent message.
 *
 * If requesting chat room activity, the client will be expected to pass in a last-read message index.
 */
class RedisChat
{
	const KEY_ROOM_LIST = '{chat:rooms}';
	const KEY_ROOM_DATA = '{chat:%s}';
	const KEY_ROOM_USERS = '{chat:%s:users}';
	const KEY_ROOM_MESSAGES = '{chat:%s:messages}';
	const KEY_USER_DATA = '{chat:user:%s}';
	const KEY_USER_ROOMS = '{chat:user:%s:rooms}';

	private $redis;

	private $room;
	private $user;

	public function __construct(Redis $redis, $user = NULL)
	{
		$this->redis = $redis;
		$this->user = empty($user) ? UUID::v4() : $user;
	}

	/**
	 * Join a chat room.
	 *
	 * @param string $room The room identifier.
	 * @param mixed $data If the room is to be created, apply this data.
	 * @return bool True if the user has successfully joined the room.
	 */
	public function join($room)
	{
		/* If the room doesn't exist, can't join it. */
		if (!$this->redis->sIsMember(self::KEY_ROOM_LIST, $room)) {
			return FALSE;
		}

		/* Add this user to the set of users in the room, and vice-versa */
		$result = $this->redis->multi(Redis::PIPELINE)
			->sAdd(sprintf(self::KEY_ROOM_USERS, $room), $this->user)
			->sAdd(sprintf(self::KEY_USER_ROOMS, $this->user), $room)
			->exec();

		/* If the call fails, the join has failed. */
		if (!$result) {
			return FALSE;
		}

		/* Add results don't matter; If the user/room is already in the room's/user's set, it isn't a problem. */

		return TRUE;
	}

	/**
	 * Leave a chat room.
	 *
	 * @param string $room The room identifier.
	 * @return bool True if the user has successfully left the room.
	 */
	public function leave($room)
	{
		/* Remove this user from the set of users in the room, and vice-versa */
		$result = $this->redis->multi(Redis::PIPELINE)
			->sRem(sprintf(self::KEY_ROOM_USERS, $room), $this->user)
			->sRem(sprintf(self::KEY_USER_ROOMS, $this->user), $room)
			->exec();

		/* If the call fails, the leave has failed. */
		if (!$result) {
			return FALSE;
		}

		/* If there are no more users, clean up the room */
		if ($this->redis->sCard(sprintf(self::KEY_ROOM_USERS, $room)) === 0) {
			$this->removeRoom($room);
		}

		return TRUE;
	}

	/**
	 * Create a chat room.
	 *
	 * @param string $room The room identifier
	 * @param string $data Any data to be associated with the room
	 * @return string On successful room creation, returns the room name. Returns false on failure.
	 */
	public function createRoom($room = NULL, $data = NULL)
	{
		/* Generate a room ID if one wasn't provided. */
		if (empty($room)) {
			$room = UUID::v4();
		}

		$num = $this->redis->sAdd(sprintf(self::KEY_ROOM_LIST), $room);

		/* If a room was added, set room data (can be empty). */
		if (!$num) {
			return FALSE;
		}
		
		$result = $this->redis->set(sprintf(self::KEY_ROOM_DATA, $room), $data);
		
		return $result ? $room : FALSE;
	}

	/**
	 * Remove a chat room.
	 *
	 * @param string $room The room identifier
	 */
	public function removeRoom($room)
	{
		$users = $this->redis->sMembers(sprintf(self::KEY_ROOM_USERS, $room));

		foreach ($users as $user) {
			$this->redis->sRem(sprintf(self::KEY_USER_ROOMS, $this->user), $room);
		}

		/* Delete the room structures and remove it from the list. */
		$this->redis->multi(Redis::PIPELINE)
			->del(sprintf(self::KEY_ROOM_DATA, $room))
			->del(sprintf(self::KEY_ROOM_USERS, $room))
			->del(sprintf(self::KEY_ROOM_MESSAGES, $room))
			->sRem(sprintf(self::KEY_ROOM_LIST), $room)
			->exec();
	}

	/**
	 * Sends a message to the room.
	 */
	public function message($room, $message)
	{
		/* Can't send if the room doesn't exist. */
		if (!$this->redis->exists(sprintf(self::KEY_ROOM_DATA, $room))) {
			return FALSE;
		}

		$this->redis->rPush(sprintf(self::KEY_ROOM_MESSAGES, $room), $message);
	}

	/**
	 * Reads unread messages from the room.
	 *
	 * @return string[] Returns the unread message, or false if there are no messages to read.
	 */
	public function retrieve($room, $index)
	{
		return $this->redis->lRange(sprintf(self::KEY_ROOM_MESSAGES, $room), $index, -1);
	}
}
/*
$redis = new Redis();
$redis->connect('127.0.0.1');

$q = new RedisChat($redis);
//var_dump($q->createRoom('test', 'this is a test chat room'));
var_dump($q->join('test'));
$q->message('test', 'test one');
var_dump($q->retrieve('test', 2));
//$q->removeRoom('test');
/*$q->send('test1');
$q->send('test2');
var_dump($q->read());
var_dump($q->read());
var_dump($q->read());
$q->send('test3');
var_dump($q->read());
*/