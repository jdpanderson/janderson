<?php

namespace janderson\protocol\websocket;

use janderson\misc\Trie;
use janderson\protocol\handler\ProtocolHandler;
use janderson\protocol\http\Request;
use janderson\protocol\http\Response;
use janderson\protocol\http\handler\Dispatcher;

/**
 * Class meant specifically to work to dispatch websocket requests.
 *
 * This class will create instances of protocol handlers for HTTP/Websocket requests to specific paths.
 */
class WebsocketDispatcher
{
	/**
	 * Create a new websocket dispatcher with a list of prefixes.
	 *
	 * @param mixed[] An associative array of string prefixes mapped to class names or callables to be dispatched (routed) when requests come in.
	 */
	public function __construct($prefixes = array())
	{
		$this->trie = new Trie();

		foreach ($prefixes as $prefix => $callable) {
			$this->addPrefix($prefix, $callable);
		}
	}

	/**
	 * Checks whether an object is dispatchable by this class.
	 *
	 * @param mixed $test The variable to test for dispatchability.
	 * @return bool True if dispatchable.
	 */
	public static function isDispatchable($test)
	{
		return (is_callable($test) || is_subclass_of($test, 'janderson\\protocol\\handler\\ProtocolHandler'));
	}

	/**
	 * Add a prefix which will trigger dispatching of the request to the given handler.
	 *
	 * @param string $prefix The path which will be dispatched to the given callable or protocol handler.
	 * @param mixed $callable This can be either a callable which is expected to return a protocol handler, or the name of a protocol handler class which will be invoked.
	 * @return bool Returns true if the prefix was successfully registered.
	 */
	public function addPrefix($prefix, $callable)
	{
		if (!$this->isDispatchable($callable)) {
			return FALSE;
		}

		$this->trie->add($prefix, $callable);
		return TRUE;
	}

	/**
	 * Get a new protocol handler instance for the given request.
	 *
	 * @param string &$buf The buffer to be passed to the protocol handler.
	 * @param int &$buflen The buffer length to be passed to the protocol handler.
	 * @param Request &$request The request object, whose path should be used to determine what protocol handler will be selected.
	 * @param Response &$response The response, if the response needs to be modified.
	 * @return ProtocolHandler A protocol handler, or false if none is available.
	 */
	public function getProtocolHandler(&$buf, &$buflen, Request &$request, Response &$response)
	{
		/* The source we're getting the protocol handler from. Either a class name or a callable. */
		$source = $this->trie->get($request->getPath());

		/* No entry. Return a 404. */
		if (!$source) {
			$response->setStatusCode(HTTP::STATUS_NOT_FOUND);
			return FALSE;
		}

		/* Simple case. A string class name; Create a new instance and return it. */
		if (is_string($source)) {
			return new $source($buf, $buflen);
		}

		/**
		 * The "add" code forces $source to be either a class name or callable, so assume it's a callable at this stage.
		 *
		 * Note 1: The return value of the callable isn't checked. The websocket handler already does that.
		 * Note 2: I consider PHP 5.4+'s callable to be nicer, so use callable syntax whenever possible. */
		if (PHP_VERSION_ID >= 50400) {
			return $source($buffer, $buflen);
		} else {
			return call_user_func_array($this->handler, array(&$buffer, &$buflen));
		}
	}
}
