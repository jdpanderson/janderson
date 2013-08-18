<?php

namespace janderson\examples\chat;

use janderson\misc\UUID;

class ChatService {
	/**
	 * A UUID used as a namespace for UUIDv5 to perform user handle to ID mappings. I.e. the user identifier is the UUIDv5 of user handle in this namespace.
	 *
	 * This is an example. If we were doing this for real, this value would have to be kept secret or it users could be spoofed.
	 */
	const NAMESPACE_UUID = "02f7d382-431a-4346-ba1c-50586be01628";

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

	protected static function crypt($password, $salt = NULL)
	{
		if (!isset($salt)) {
			$chars = array_merge(array(".", "/"), range("A", "Z"), range("a", "z"), range("0", "9"));
			var_dump($chars);
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
		if (!isset($request->handle, $request->secret)) {
			return FALSE;
		}

		/* The user's UUID is a UUIDv5 in our namespace of the user's handle. */
		$id = UUID::v5(self::NAMESPACE_UUID, $request->handle);

		$user = self::getChat()->getUser($id);

		if ($user) {
			if ($user->passwd != self::crypt($request->secret, $user->passwd)) {
				var_dump($user, self::crypt($request->secret, $user->passwd));
				return FALSE;
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

		return self::getChat()->getRooms();
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
		} elseif (!self::getChat()->isInRoom($request->id, $request->room)) {
			return FALSE;
		}

		return self::getChat()->getUsers($request->room);
	}

	/**
	 * Create a new chat room.
	 *
	 * @param Object $request The request object.
	 * @return Object The response object.
	 */
	public static function createRoom($request)
	{
		echo "Creating room...\n";
		if (!isset($request->room, $request->data, $request->id)) {
			return FALSE;
		} elseif (!self::getChat()->getUser($request->id)) {
			return FALSE;
		}


		echo "Creating room 2...\n";

		$room = self::getChat()->createRoom($request->room, $request->data);
		$join = self::getChat()->join($request->id, $request->room);

		var_dump($room);

		return $room;
	}

	/**
	 * Join a room.
	 */
	public static function join($request)
	{
		if (!isset($request->id, $request->room)) {
			return FALSE;
		} elseif (!self::getChat()->getUser($request->id)) {
			return FALSE;
		}

		return self::getChat()->join($request->id, $request->room);
	}

	/**
	 * Leave a room.
	 */
	public static function leave($request)
	{
		if (!isset($request->id, $request->room)) {
			return FALSE;
		} elseif (!self::getChat()->isInRoom($request->id, $request->room)) {
			return FALSE;
		}

		return self::getChat()->leave($request->id, $request->room);
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
		} elseif (!self::getChat()->isInRoom($request->id, $request->room)) {
			return FALSE;
		}

		return self::getChat()->message($request->id, $request->room, $request->message);
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
		} elseif (!self::getChat()->isInRoom($request->id, $request->room)) {
			return FALSE;
		}

		if (!isset($request->index)) {
			$request->index = 0;
		}

		return self::getChat()->retrieve($request->room, $request->index);
	}
}