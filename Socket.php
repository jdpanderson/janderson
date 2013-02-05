<?php

namespace janderson\net;

/**
 * Object-Oriented wrapper around the PHP Socket interface.
 */
class Socket {
	/**
	 *
	 */
	const ADDR_ANY = "0.0.0.0";

	/**
	 *
	 */
	const PORT_ANY = 0;

	/**
	 * @return array
	 */
	public function getError() {
		$errno = isset($this->socket) ? socket_last_error($this->socket) : socket_last_error();
		$error = socket_strerror($errno);

		return array($errno, $error);
	}

	/**
	 * @param int $domain Protocol family, usually AF_INET or AF_INET6. Also accepts a socket resource to be wrapped.
	 * @param int $type
	 * @param int $proto
	 */
	public function __construct($domain = AF_INET, $type = SOCK_STREAM, $proto = SOL_TCP) {
		if (is_resource($domain) && get_resource_type($domain) == "Socket") {
			$this->socket = $domain;
			return;
		}

		$this->socket = socket_create($domain, $type, $proto);

		if (!$this->socket) {
			list($errno, $error) = $this->getError();
			throw new Exception("Unable to create socket: $error ($errno)");
		}
	}

	/**
	 * @return bool|Socket
	 */
	public function accept() {
		$child = socket_accept($this->socket);

		if ($child === FALSE) return FALSE;

		return new Socket($child);
	}

	/**
	 * @param string $address
	 * @param int $port
	 * @return bool
	 */
	public function bind($address = self::ADDR_ANY, $port = self::PORT_ANY) {
		return socket_bind($this->socket, $address, $port);
	}

	/**
	 *
 	 */
	public function close() {
		return socket_close($this->socket);
	}

	public function listen($backlog = 0, $address = NULL, $port = NULL) {
		if (!$this->setOption(SO_REUSEADDR, 1)) return FALSE;

		if (isset($address) || isset($port)) {
			if (!$this->bind($address, $port)) return FALSE;
		}

		if (!socket_listen($this->socket, $backlog)) return FALSE;

		return TRUE;
	}

	/**
	 * @param $option
	 * @param $value
	 * @return bool
	 */
	public function setOption($option, $value) {
		// XXX this could detect known options and adjust SOL_SOCKET. For now, no use case for anything other than SOL_SOCKET
		return socket_set_option($this->socket, SOL_SOCKET, $option, $value);
	}

	/**
	 * @param $option
	 * @return mixed
	 */
	public function getOption($option) {
		return socket_get_option($this->socket, SOL_SOCKET, $option);
	}

	/**
	 * @return array|bool
	 */
	public function getPeerName() {
		$result = socket_getpeername($this->socket, $addr, $port);
		return $result ? array($addr, $port) : FALSE;
	}

	/**
	 * @return array|bool
	 */
	public function getSockName() {
		$result = socket_getsockname($this->socket, $addr, $port);
		return $result ? array($addr, $port) : FALSE;
	}

	/**
	 * @param bool $blocking
	 * @return bool
	 */
	public function setBlocking($blocking = TRUE) {
		return $blocking ? socket_set_block($this->socket) : socket_set_nonblock($this->socket);
	}

	/**
	 * @param null $read
	 * @param null $write
	 * @param null $except
	 * @param int $tv_sec
	 * @param int $tv_usec
	 * @return int
	 */
	public static function select(&$read = NULL, &$write = NULL, &$except = NULL, $tv_sec = 0, $tv_usec = 0) {
		$map = array();

		foreach (array('read', 'write', 'except') as $arrName) {
			if (!is_array($$arrName)) continue;
			foreach ($$arrName as &$s) {
				if ($s instanceof Socket) {
					$socket = $s->getResource();
					$map[(string)$socket] = $s;
					$s = $socket;
				}
			}
		}

		$result = socket_select($read, $write, $except, $tv_sec, $tv_usec);

		foreach (array('read', 'write', 'except') as $arrName) {
			if (!is_array($$arrName)) continue;
			foreach ($$arrName as &$s) {
				if (isset($map[(string)$s])) {
					$s = $map[(string)$s];
				}
			}
		}

		return $result;
	}

	/**
	 * @param $len
	 * @param null $flags
	 * @return array
	 */
	public function recv($len, $flags = NULL) {
		$len = socket_recv($this->socket, $buf, $len, $flags);

		return array($buf, $len);
	}

	/**
	 * @param $buf
	 * @param $len
	 * @param null $flags
	 * @return int
	 */
	public function send($buf, $len, $flags = NULL) {
		return socket_send($this->socket, $buf, $len, $flags);
	}

	/**
	 * @return resource
	 */
	public function getResource() {
		return $this->socket;
	}

	/**
	 * @return int
	 * @throws Exception
	 */
	public function getResourceId() {
		$str = (string)$this->socket;

		$tok = explode("#", $str, 2);

		if (count($tok) != 2 || $tok[0] != "Resource id") throw new Exception("String format has changed: Cannot parse resource ID");

		return (int)$tok[1];
	}
}
