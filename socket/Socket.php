<?php

namespace janderson\net\socket;

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

	const ERRMODE_COMPAT = 0;
	const ERRMODE_EXCEPTION = 1;

	/**
	 * @return array
	 */
	public function getError() {
		$errno = isset($this->socket) ? socket_last_error($this->socket) : socket_last_error();
		$error = socket_strerror($errno);

		return array($errno, $error);
	}

	private function error($function) {
		if ($this->errorMode == self::ERRMODE_EXCEPTION) {
			list($errno, $error) = $this->getError();

			throw new Exception(sprintf("%s::%s: %s", __CLASS__, $function, $error), $errno);
		}

		return FALSE;
	}

	protected $errorMode = self::ERRMODE_EXCEPTION;

	protected $socket;

	/**
	 * A socket address family, usually AF_INET or AF_INET6
	 *
	 * @var int
	 */
	protected $domain;

	/**
	 * A socket type, usually SOCK_STREAM or SOCK_DGRAM, though other values are accepted.
	 *
	 * @var int
	 */
	protected $type;

	/**
	 * @var int
	 */
	protected $protocol;
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

		$this->domain = $domain;
		$this->protocol = $proto;
		$this->type = $type;

		$this->socket = socket_create($domain, $type, $proto);

		if ($this->socket === FALSE) {
			return $this->error(__FUNCTION__);
		}
	}

	public function getDomain() {
		return $this->domain;
	}

	public function getType() {
		return $this->type;
	}

	public function getProtocol() {
		return $this->protocol;
	}
	/**
	 * @return bool|Socket
	 */
	public function accept() {
		$child = socket_accept($this->socket);

		if ($child === FALSE) {
			return $this->error(__FUNCTION__);
		}

		return new static($child);
	}

	/**
	 * @param string $address
	 * @param int $port
	 * @throws Exception
	 * @return bool
	 */
	public function bind($address = self::ADDR_ANY, $port = self::PORT_ANY) {
		return socket_bind($this->socket, $address, $port) ? TRUE : $this->error(__FUNCTION__);
	}

	/**
	 * @return void
 	 */
	public function close() {
		socket_close($this->socket);
	}

	public function listen($backlog = 0, $address = NULL, $port = NULL) {
		try {
			$this->setOption(SO_REUSEADDR, 1);
		} catch (Exception $e) {}

		if (isset($address) || isset($port)) {
			if (!$this->bind($address, $port)) {
				return $this->error(__FUNCTION__);
			}
		}

		if (!socket_listen($this->socket, $backlog)) {
			return $this->error(__FUNCTION__);
		}

		return TRUE;
	}

	/**
	 * @param $option
	 * @param $value
	 * @return bool
	 */
	public function setOption($option, $value) {
		// XXX this could detect known options and adjust SOL_SOCKET. For now, no use case for anything other than SOL_SOCKET
		return socket_set_option($this->socket, SOL_SOCKET, $option, $value) ? TRUE : $this->error(__FUNCTION__);
	}

	/**
	 * @param $option
	 * @return mixed
	 */
	public function getOption($option) {
		$result = socket_get_option($this->socket, SOL_SOCKET, $option);

		return ($result !== FALSE) ? $result : $this->error(__FUNCTION__);
	}

	/**
	 * @return array|bool
	 */
	public function getPeerName() {
		$result = socket_getpeername($this->socket, $addr, $port);
		return $result ? array($addr, $port) : $this->error(__FUNCTION__);
	}

	/**
	 * @return array|bool
	 */
	public function getSockName() {
		$result = socket_getsockname($this->socket, $addr, $port);
		return $result ? array($addr, $port) : $this->error(__FUNCTION__);
	}

	/**
	 * @param bool $blocking
	 * @return bool
	 */
	public function setBlocking($blocking = TRUE) {
		$result = $blocking ? socket_set_block($this->socket) : socket_set_nonblock($this->socket);

		return ($result !== FALSE) ? $result : $this->error(__FUNCTION__);
	}

	/**
	 * @param null $read
	 * @param null $write
	 * @param null $except
	 * @param int $tv_sec
	 * @param int $tv_usec
	 * @return int
	 */
	public function select(&$read = NULL, &$write = NULL, &$except = NULL, $tv_sec = 0, $tv_usec = 0) {
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

		if ($result === FALSE) {
			return $this->error(__FUNCTION__);
		}

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

		return ($len !== FALSE) ? array($buf, $len) : $this->error(__FUNCTION__);
	}

	/**
	 * @param $buf
	 * @param $len
	 * @param null $flags
	 * @return int
	 */
	public function send($buf, $len, $flags = NULL) {
		$len = socket_send($this->socket, $buf, $len, $flags);

		return ($len !== FALSE) ? $len : $this->error(__FUNCTION__);
	}

	const SHUT_RD = 0;
	const SHUT_WR = 1;
	const SHUT_RDWR = 2;

	/**
	 * @param int $how
	 * @return bool
	 */
	public function shutdown($how = self::SHUT_RD) {
		return (socket_shutdown($this->socket, $how)) ? TRUE : $this->error(__FUNCTION__);
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
		return intval($this->socket);
	}
}
