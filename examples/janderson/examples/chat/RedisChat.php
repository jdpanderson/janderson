<?php

namespace janderson\examples\chat;

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
 *
 * Also of note: Room and user data are JSON-encoded blobs. Implementations should use this data to handle behaviors required, e.g. authentication data or moderator info could be stored in the blobs, and possibly filtered before returning this data to users.
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

	public function __construct(Redis $redis)
	{
		$this->redis = $redis;
	}

	/**
	 * Get a list of chat rooms and their data.
	 *
	 * @return mixed[] An array of room identifiers mapped to their room data.
	 */
	public function getRooms()
	{
		$rooms = $this->redis->sMembers(self::KEY_ROOM_LIST);

		if (empty($rooms)) {
			return array();
		}

		$this->redis->multi(Redis::PIPELINE);
		foreach ($rooms as $room) {
			$this->redis->get(sprintf(self::KEY_ROOM_DATA, $room));
		}
		$results = $this->redis->exec();


		return array_combine($rooms, array_map("json_decode", $results));
	}

	/**
	 * Get a list of chat rooms and their data.
	 *
	 * @return mixed[] An array of room identifiers to which the user is joined.
	 */
	public function getUserRooms($user)
	{
		return $this->redis->sMembers(sprintf(self::KEY_USER_ROOMS, $user));
	}

	/**
	 * Check is a user has joined a particular room.
	 */
	public function isInRoom($user, $room)
	{
		return $this->redis->sIsMember(sprintf(self::KEY_USER_ROOMS, $user), $room);
	}

	/**
	 * Gets information associated with users in a chat room.
	 *
	 * @param string $room The room identifier.
	 * @return mixed[] An array of user identifiers mapped to their user data.
	 */
	public function getUsers($room)
	{
		$users = $this->redis->sMembers(sprintf(self::KEY_ROOM_USERS, $room));

		if (empty($users)) {
			return array();
		}

		$this->redis->multi(Redis::PIPELINE);
		foreach ($users as $user) {
			$this->redis->get(sprintf(self::KEY_USER_DATA, $user));
		}
		$results = $this->redis->exec();

		return array_combine($users, array_map("json_decode", $results));
	}

	/**
	 * Gets information associated with a user.
	 *
	 * @param string $user The user's identifier.
	 * @return mixed Any user data that was stored for the user.
	 */
	public function getUser($user)
	{
		$data = $this->redis->get(sprintf(self::KEY_USER_DATA, $user));

		if (!$data) {
			return $data;
		}

		return json_decode($data);
	}

	/**
	 * Sets information associated with a user.
	 *
	 * @param string $user The user's identifier.
	 * @param mixed $data Any user data that should be stored for the user.
	 */
	public function setUser($user, $data)
	{
		return $this->redis->set(sprintf(self::KEY_USER_DATA, $user), json_encode($data));
	}

	/**
	 * Leave all channels and remove user data.
	 *
	 * @param string $user The user's identifier.
	 */
	public function removeUser($user) 
	{
		$rooms = $this->getUserRooms($user);
		foreach ($rooms as $room) {
			$this->leave($user, $room);
		}
		$result = $this->redis->multi(Redis::PIPELINE)
			->del(sprintf(self::KEY_USER_DATA, $user))
			->del(sprintf(self::KEY_USER_ROOMS, $user))
			->exec();

		return TRUE;
	}

	/**
	 * Join a chat room.
	 *
	 * @param string $room The room identifier.
	 * @param mixed $data If the room is to be created, apply this data.
	 * @return bool True if the user has successfully joined the room.
	 */
	public function join($user, $room)
	{
		/* If the room doesn't exist, can't join it. */
		if (!$this->redis->sIsMember(self::KEY_ROOM_LIST, $room)) {
			return FALSE;
		}

		/* Add this user to the set of users in the room, and vice-versa */
		$result = $this->redis->multi(Redis::PIPELINE)
			->sAdd(sprintf(self::KEY_ROOM_USERS, $room), $user)
			->sAdd(sprintf(self::KEY_USER_ROOMS, $user), $room)
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
	public function leave($user, $room)
	{
		/* Remove this user from the set of users in the room, and vice-versa */
		$result = $this->redis->multi(Redis::PIPELINE)
			->sRem(sprintf(self::KEY_ROOM_USERS, $room), $user)
			->sRem(sprintf(self::KEY_USER_ROOMS, $user), $room)
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
		
		$result = $this->redis->set(sprintf(self::KEY_ROOM_DATA, $room), json_encode($data));
		
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
			$this->redis->sRem(sprintf(self::KEY_USER_ROOMS, $user), $room);
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
	public function message($user, $room, $message)
	{
		/* Can't send if the room doesn't exist or the user hasn't joined. */
		if (!$this->redis->sIsMember(sprintf(self::KEY_ROOM_USERS, $room), $user)) {
			return FALSE;
		}

		$this->redis->rPush(sprintf(self::KEY_ROOM_MESSAGES, $room), json_encode($message));

		return TRUE;
	}

	/**
	 * Reads unread messages from the room.
	 *
	 * @return [int, string, string][] Returns the unread message, or false if there are no messages to read.
	 */
	public function retrieve($room, $index)
	{
		$messages = $this->redis->lRange(sprintf(self::KEY_ROOM_MESSAGES, $room), $index, -1);

		if (!$messages) {
			return $messages;
		}
		return array_map("json_decode", $messages);
	}

	public function getMessageCount($room)
	{
		return $this->redis->lLen(sprintf(self::KEY_ROOM_MESSAGES, $room));
	}

	public function subscribe($id, $room, $callback)
	{

	}

	public function unsubscribe($id, $room) 
	{

	}
}