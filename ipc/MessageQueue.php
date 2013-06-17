<?php

namespace janderson\ipc;

use \InvalidArgumentException;
use \janderson\misc\Destroyable;

class MessageQueue implements Destroyable
{
	const MSG_TYPE_ANY = 0;
	const MSG_TYPE_DEFAULT = 1;
	
	/**
	 * Permissions are user-only.
	 */
	const PERM = 0600;

	protected $queue;
	protected $maxlen;
	protected $serialize;

	public function __construct($key = NULL, $maxlen = 1024, $serialize = TRUE)
	{
		$key = IPCKey::create($key);

		if (!is_int($maxlen) || $maxlen < 0) {
			throw new InvalidArgumentException("Maximum message length must be a positive integer.");
		}
		$this->maxlen = (int)$maxlen;
		$this->serialize = (bool)$serialize;

		if ($this->create($key) === FALSE) {
			throw new IPCException("Failed to attach message queue");
		}	
	}

	protected function create($key)
	{
		return ($this->queue = msg_get_queue($key, 0600));
	}

	public function send($message, $type = self::MSG_TYPE_DEFAULT)
	{
		return msg_send($this->queue, IPCKey::create($type), $message, $this->serialize, FALSE);
	}

	public function receive($type = self::MSG_TYPE_ANY)
	{
		$message = $rtype = $errcode = NULL;
		$result = msg_receive($this->queue, IPCKey::create($type), $rtype, $this->maxlen, $message, $this->serialize, MSG_IPC_NOWAIT | MSG_NOERROR, $errcode);

		if (!$result) {
			return FALSE;
		}

		return $message;
	}

	/**
	 * Destroys the message queue underlying this instance.
	 */
	public function destroy()
	{
		if (isset($this->queue)) {
			msg_remove_queue($this->queue);
			$this->queue = NULL;
		}
	}
}