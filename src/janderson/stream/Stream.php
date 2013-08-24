<?php
/**
 * This code is incomplete.
 *
 * The idea is to provide compatible interfaces between sockets and streams such that either mechanism could be used for serving. This is 0% complete.
 */

namespace janderson\stream;

class Stream
{
	const MODE_READ = 'r';
	const MODE_WRITE = 'w';
	const MODE_BOTH = 'r+';
	const MODE_BOTH_TRUNCATE = 'w+';
	const MODE_WRITE_APPEND = 'a';
	const MODE_BOTH_APPEND = 'a+';
	const MODE_WRITE_EXCLUSIVE = 'x';
	const MODE_BOTH_EXCLUSIVE = 'x+';
	const MODE_WRITE_CREATE = 'c';
	const MODE_BOTH_CREATE = 'c+';

	/**
	 * @var resource
	 */
	protected $resource;

	protected $error = "";
	protected $errno = 0;

	public static function server($addr, $flags = 0, $context = NULL)
	{
		$resource = isset($context) ? stream_socket_server($addr, $errno, $error, $flags, $context) : stream_socket_server($addr, $errno, $error, $flags);

		if ($resource === FALSE) {
			throw new StreamException("Failed to create server stream: {$error}", $errno);
		}

		return new self($resource);
	}

	public function __construct($file, $mode = self::MODE_READ, $context = NULL)
	{
		if (is_resource($file)) {
			$this->resource = $file;
		} else {
			$this->resource = $this->open($file, $mode, $context);
		}
		
	}

	private function open($file, $mode, $context)
	{
		$handler = set_error_handler([$this, 'setError'], E_WARNING);
		$resource = isset($context) ? fopen($file, $mode, FALSE, $context) : fopen($file, $mode);
		set_error_handler($handler);

		if ($resource === FALSE) {
			throw new StreamException("Failed to create stream: {$this->error}", $this->errno);
		}

		return $resource;
	}

	/**
	 * Set the last error to have occurred in this case. (Usually used as an error handler callback.)
	 *
	 * @param long $errno
	 * @param string $error
	 */
	public function setError($errno, $error)
	{
		$this->error = $error;
		$this->errno = $errno;
	}

	/**
	 * Get the underlying resource associated with this stream.
	 *
	 * @return resource
	 */
	public function getResource()
	{
		return $this->resource;
	}

	/**
	 * Close this stream.
	 */
	public function close()
	{
		if ($this->resource) {
			fclose($this->resource);
			$this->resource = NULL;
		}
	}
}