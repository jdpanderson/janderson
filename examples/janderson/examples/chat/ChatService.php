<?php

namespace janderson\examples\chat;

use janderson\misc\UUID;

class ChatService {
	/**
	 * A UUID used as a namespace for UUIDv5 to perform user handle to ID mappings. I.e. the user identifier is the UUIDv5 of user handle in this namespace.
	 *
	 * This is an example. If we were doing this for real, this value would have to be kept secret or it users could be spoofed.
	 */
	const UUID_NS_USER = "02f7d382-431a-4346-ba1c-50586be01628";
	const UUID_NS_ROOM = "d077c6de-1bc1-4076-a42b-4032f1e642fe";

	private static $chat;

	protected static function getChat()
	{
		if (!isset(static::$chat)) {
			$redis = new \Redis();
			$redis->connect('127.0.0.1');

			static::$chat = new RedisChat($redis);
		}

		return static::$chat;
	}

	/**
	 * A wrapper around PHP's crypt that will also generate a bcrypt salt by default if no salt is provided.
	 */
	protected static function crypt($password, $salt = NULL)
	{
		if (!isset($salt)) {
			$chars = array_merge(array(".", "/"), range("A", "Z"), range("a", "z"), range("0", "9"));
			$salt = "$2a$07$";
			for ($i = 0; $i < 22; $i++) {
				$salt .= $chars[mt_rand(0, 63)];
			}
			$salt .= "$";
		}

		return crypt($password, $salt);
	}

	/**
	 * A register request will take a name and password, and either create or retrieve a user.
	 */
	public static function register($request)
	{
		if (empty($request->handle) || empty($request->secret)) {
			throw new Exception("Nickname and password must not be empty.");
		}

		/* The user's UUID is a UUIDv5 in our namespace of the user's handle. */
		$id = UUID::v5(self::UUID_NS_USER, $request->handle);

		$user = self::getChat()->getUser($id);

		if ($user) {
			if ($user->passwd != self::crypt($request->secret, $user->passwd)) {
				throw new \Exception("Already registered, or password incorrect.");
			}
		} else {
			$user = new \stdClass();
			$user->handle = $request->handle;
			$user->passwd = self::crypt($request->secret);
			$user->id = $id;

			self::getChat()->setUser($user->id, $user);
		}

		return $user->id;
	}

	/**
	 * Forget about the existence of this user.
	 *
	 * @param Object $request The request object.
	 * @return Object The response object.
	 */
	public static function unregister($request)
	{
		if (!isset($request->id)) {
			return FALSE;
		}

		return self::getChat()->removeUser($request->id);
	}

	/**
	 * Get a list of rooms available.
	 *
	 * @param Object $request The request object.
	 * @return Object The response object.
	 */
	public static function getRooms($request)
	{
		if (!$request->id) {
			return FALSE;
		} elseif (!self::getChat()->getUser($request->id)) {
			return FALSE;
		}

		$rooms = self::getChat()->getRooms();

		$return = array();
		foreach ($rooms as $room => $data) {
			$return[] = $data->name;
		}

		return $return;
	}

	/**
	 * Get a list of users in a room.
	 *
	 * @param Object $request The request object.
	 * @return Object The response object.
	 */
	public static function getUsers($request)
	{
		if (!isset($request->room, $request->id)) {
			return FALSE;
		}

		$room = UUID::v5(self::UUID_NS_ROOM, $request->room);

		if (!self::getChat()->isInRoom($request->id, $room)) {
			return FALSE;
		}

		$users = self::getChat()->getUsers($room);

		$return = array();
		foreach ($users as $user => $data) {
			$return[] = $user->handle;
		}

		return $return;
	}

	/**
	 * Create a new chat room.
	 *
	 * @param Object $request The request object.
	 * @return Object The response object.
	 */
	public static function createRoom($request)
	{
		if (!isset($request->room, $request->data, $request->id)) {
			return FALSE;
		} elseif (!self::getChat()->getUser($request->id)) {
			return FALSE;
		}

		$data = new \stdClass();
		$data->name = $request->room;
		$data->description = $request->data;
		$data->id = UUID::v5(self::UUID_NS_ROOM, $request->room);

		$room = self::getChat()->createRoom($data->id, $data);
		$join = self::getChat()->join($request->id, $data->id);

		return $request->room;
	}

	/**
	 * Join a room, leaving any previous room(s).
	 */
	public static function join($request)
	{
		if (!isset($request->id, $request->room)) {
			return FALSE;
		} elseif (!self::getChat()->getUser($request->id)) {
			return FALSE;
		}

		$rooms = self::getChat()->getUserRooms($request->id);
		foreach ($rooms as $room) {
			self::getChat()->leave($request->id, $room);
		}

		$room = UUID::v5(self::UUID_NS_ROOM, $request->room);

		if (!self::getChat()->join($request->id, $room)) {
			return FALSE;
		}

		return self::getChat()->getMessageCount($room);
	}

	/**
	 * Leave a room.
	 */
	public static function leave($request)
	{
		if (!isset($request->id, $request->room)) {
			return FALSE;
		}

		$room = UUID::v5(self::UUID_NS_ROOM, $request->room);

		if (!self::getChat()->isInRoom($request->id, $room)) {
			return FALSE;
		}


		return self::getChat()->leave($request->id, $room);
	}

	/**
	 * Add a message to a room. (Send a message to room users.)
	 *
	 * @param Object $request The request object.
	 * @return Object The response object.
	 */
	public static function message($request)
	{
		if (!isset($request->id, $request->room, $request->message)) {
			return FALSE;
		}

		$room = UUID::v5(self::UUID_NS_ROOM, $request->room);

		if (!self::getChat()->isInRoom($request->id, $room)) {
			return FALSE;
		}

		$user = self::getChat()->getUser($request->id);

		$message = array(time(), $user->handle, $request->message);

		return self::getChat()->message($request->id, $room, $message);
	}

	/**
	 * Get messages in a room.
	 *
	 * @param Object $request The request object.
	 * @return Object The response object.
	 */
	public static function messages($request)
	{
		if (!isset($request->id, $request->room)) {
			return FALSE;
		}

		$room = UUID::v5(self::UUID_NS_ROOM, $request->room);

		if (!self::getChat()->isInRoom($request->id, $room)) {
			return FALSE;
		}

		if (!isset($request->index)) {
			$request->index = 0;
		}

		return self::getChat()->retrieve($room, $request->index);
	}

	/**
	 * Get the number of messages held in a room
	 *
	 * @param Object $request The request object.
	 * @return Object The response object.
	 */
	public static function getMessageCount($request)
	{
		if (!isset($request->id, $request->room)) {
			return FALSE;
		}

		$room = UUID::v5(self::UUID_NS_ROOM, $request->room);

		if (!self::getChat()->isInRoom($request->id, $room)) {
			return FALSE;
		}

		return self::getChat()->getMessageCount($room);
	}
}