<?php

namespace janderson\examples\chat;

use janderson\protocol\handler\ProtocolHandler;

/**
 * Example handler, echoes any input until the client disconnects.
 *
 * This should be compatible with RFC862, but this has not been tested.
 */
class ChatHandler implements ProtocolHandler
{
	/**
	 * A local reference to the write buffer.
	 */
	protected $buffer;

	/**
	 * A local reference to the write buffer length.
	 */
	protected $bufferlen;

	protected $error = FALSE;

	/**
	 * Prepare to handle the echo protocol!
	 *
	 * @param string &$buffer A reference to the write buffer.
	 * @param int &$bufferlen A reference to the write buffer length (ignored - server can handle it)
	 */
	public function __construct(&$buffer, &$bufferlen)
	{
		$this->buffer = &$buffer;
		$this->bufferlen = &$bufferlen;
	}

	/**
	 * On read complete, do a method call somewhat like JSON-RPC, except the request object is parroted back with result/error tacked on.
	 *
	 * @param string $buffer The newly received data.
	 * @param int $length The length of data received. Ignored.
	 * @return bool True. The service will continue to echo until the client disconnects.
	 */
	public function read($buffer, $length)
	{
		$obj = json_decode($buffer);

		if (!$obj) return $this->sendMessage(new \stdClass(), NULL, "JSON decode failure");
		if (!isset($obj->method)) return $this->sendMessage($obj, NULL, "No method requested");

		$callable = array('janderson\\examples\\chat\\ChatService', $obj->method);
		if (!is_callable($callable)) return $this->sendMessage($obj, NULL, "Not a valid method");

		$params = isset($obj->params) ? $obj->params : array();

		try {
			$return = call_user_func_array($callable, $params);
		} catch (Exception $e) {
			return $this->sendMessage($obj, NULL, $e->getMessage());
		}

		$obj->result = $return;
		$buffer = json_encode($obj);
		$this->buffer .= $buffer;
		$this->bufferlen += strlen($buffer);

		return TRUE;
	}

	/**
	 * Once we're done writing, disconnect if there's been an error.
	 *
	 * @return bool True if the connection should remain alive, false otherwise.
	 */
	public function write() {
		if (!empty($this->buffer)) {
			return TRUE;
		}

		return $this->error ? FALSE : TRUE;
	}

	/**
	 * On close, do nothing.
	 */
	public function close() {}

	private function sendMessage($object, $result = NULL, $error = NULL) {
		$this->error = TRUE;
		$object->result = $result;
		$object->error = $error;
		$buffer = json_encode($object);

		$this->buffer .= $buffer;
		$this->bufferlen += strlen($buffer);

		return TRUE;
	}
}
