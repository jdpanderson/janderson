<?php
/**
 * This file defines the HTTP class, which includes definitions for a subset of RFC2616.
 */
namespace janderson\http;

/**
 * HTTP
 */
class HTTP {
	const EOL = "\r\n";

	const VERSION_0_9 = "0.9";
	const VERSION_1_0 = "1.0";
	const VERSION_1_1 = "1.1";

	const METHOD_GET = 'GET';
	const METHOD_POST = 'POST';
	const METHOD_HEAD = 'HEAD';
	const METHOD_PUT = 'PUT';
	const METHOD_DELETE = 'DELETE';
	const METHOD_OPTIONS = 'OPTIONS';
	const METHOD_TRACE = 'TRACE';

	const STATUS_CONTINUE = 100;
	const STATUS_OK = 200;
	const STATUS_MOVED_PERMANENTLY = 301;
	const STATUS_FOUND = 302;
	const STATUS_NOT_MODIFIED = 304;
	const STATUS_BAD_REQUEST = 400;
	const STATUS_UNAUTHORIZED = 401;
	const STATUS_FORBIDDEN = 403;
	const STATUS_NOT_FOUND = 404;
	const STATUS_PROXY_AUTH_REQUIRED = 407;
	const STATUS_REQUEST_TIMEOUT = 408;
	const STATUS_INTERNAL_SERVER_ERROR = 500;
	const STATUS_SERVICE_UNAVAILABLE = 503;
	const STATUS_VERSION_NOT_SUPPORTED = 505;

	protected static $methods = array(
		self::METHOD_GET,
		self::METHOD_POST,
		self::METHOD_HEAD,
		self::METHOD_PUT,
		self::METHOD_DELETE,
		self::METHOD_OPTIONS,
		self::METHOD_TRACE
	);

	public static function getMethods() {
		return static::$methods;
	}

	/**
	 * Get a list of supported HTTP versions
	 */
	public static function getVersions() {
		return array(self::VERSION_0_9, self::VERSION_1_0, self::VERSION_1_1);
	}

	/**
	 * @param int $statusCode The HTTP status code to translate into a string
	 */
	public static function getStatusString($statusCode) {
		static $strings = array(
			self::STATUS_CONTINUE => "Continue",
			self::STATUS_OK => "OK",
			self::STATUS_MOVED_PERMANENTLY => "Moved Permanently",
			self::STATUS_FOUND => "Found",
			self::STATUS_NOT_MODIFIED => "Not Modified",
			self::STATUS_BAD_REQUEST => "Bad Request",
			self::STATUS_UNAUTHORIZED => "Unauthorized",
			self::STATUS_FORBIDDEN => "Forbidden",
			self::STATUS_NOT_FOUND => "Not Found",
			self::STATUS_PROXY_AUTH_REQUIRED => "Proxy Authentication Required",
			self::STATUS_REQUEST_TIMEOUT => "Request Timeout",
			self::STATUS_INTERNAL_SERVER_ERROR => "Internal Server Error",
			self::STATUS_SERVICE_UNAVAILABLE => "Service Unavailable",
			self::STATUS_VERSION_NOT_SUPPORTED => "Version Not Supported",
		);

		return isset($strings[$statusCode]) ? $strings[$statusCode] : "Undefined";
	}
}
