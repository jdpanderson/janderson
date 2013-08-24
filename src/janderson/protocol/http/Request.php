<?php

namespace janderson\protocol\http;

/**
 * Class representing an HTTP request.
 */
class Request
{
	protected $headers;
	protected $method;
	protected $uri;
	protected $version;
	protected $content;
	protected $path;
	protected $query;
	protected $fragment;

	public function __construct($method, $uri, $version, $headers = array(), $content = NULL)
	{
		if (!in_array($method, HTTP::getMethods())) {
			throw new HTTPException("Invalid method", HTTP::STATUS_BAD_REQUEST);
		}

		$version = explode('/', $version); // HTTP/x.x
		if (count($version) != 2 || $version[0] != 'HTTP' || !in_array($version[1], HTTP::getVersions())) {
			throw new HTTPException("Unsupported version", HTTP::STATUS_VERSION_NOT_SUPPORTED);
		}

		$this->method = $method;
		$this->version = $version[1];
		$this->headers = $headers;
		$this->content = $content;

		$this->setURI($uri);
	}

	/**
	 * Get the content received with the request.
	 *
	 * @return string
	 */
	public function getContent()
	{
		return $this->content;
	}

	/**
	 * Set the content associated with this request.
	 *
	 * @param string $content New content string.
	 */
	public function setContent($content)
	{
		$this->content = $content;
	}

	/**
	 * Replace the headers in this request with a new set of headers.
	 *
	 * @param string[] $headers An associative array of headers.
	 */
	public function setHeaders($headers = array())
	{
		$this->headers = $headers;
	}

	/**
	 * Based on this request, should the connection be kept alive?
	 *
	 * @return bool True if the connection should be kept open.
	 */
	public function keepAlive()
	{
		$connection = isset($this->headers['connection']) ? strtolower($this->headers['connection']) : NULL;
		/**
		 * HTTP <1.1 is close by default, unless otherwise specified.
		 * HTTP =1.1 is keep-alive by default, unless otherwise specified.
		 */
		if ($this->version == HTTP::VERSION_1_1) {
			return $connection != 'close';
		} else {
			return $connection == 'keep-alive';
		}
	}

	/**
	 * Get a value for a header, or FALSE if not set.
	 *
	 * @param $header
	 */
	public function getHeader($header)
	{
		$header = strtolower($header);

		return isset($this->headers[$header]) ? $this->headers[$header] : FALSE;
	}

	/**
	 * Get the content length, or 0 if there is no content.
	 *
	 * @return int
	 */
	public function getContentLength()
	{
		return isset($this->headers['content-length']) ? (int)$this->headers['content-length'] : 0;
	}

	public function getMethod() {
		return $this->method;
	}

	/**
	 * Get the full URI from the request, including possible path, query, and fragment.
	 *
	 * @return string
	 */
	public function getURI()
	{
		return $this->uri;
	}

	/**
	 * Set the requested URI, which should be at least a path.
	 *
	 * @param string $uri
	 */
	public function setURI($uri)
	{
		$this->uri = $uri;

		$parts = parse_url($uri);
		$this->path = isset($parts['path']) ? $parts['path'] : '/';
		$this->query = isset($parts['query']) ? $parts['query'] : '';
		$this->fragment = isset($parts['fragment']) ? $parts['fragment'] : '';
	}

	/**
	 * Get a string representing the HTTP version, typically "1.0" or "1.1", but may rarely be "0.9"
	 *
	 * @return string $version The HTTP version used in the request
	 */
	public function getVersion()
	{
		return $this->version;
	}
}
