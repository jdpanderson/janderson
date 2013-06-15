<?php
/**
 * This file defines the StaticDispatcher interface
 */
namespace janderson\http;

use \janderson\socket\server\Dispatchable;

/**
 * StaticDispatcher is a dispatcher for static content, i.e. serves files from a directory tree in the filesystem.
 */
class StaticDispatcher implements Dispatchable {
	protected static $contentTypeMap = array(
		'html' => 'text/html',
		'txt'  => 'text/plain',
		'js'   => 'text/javascript',
		'css'  => 'text/css',
	);

	/**
	 * The path to the file root. Essentially the document root.
	 *
	 * @var string
	 */
	protected $path;

	public function __construct($path = NULL) {
		$this->path = realpath($path);

		if (!$this->path) {
			throw new Exception("Path not found");
		}

		if (!is_dir($this->path)) {
			$this->path = dirname($this->path);
		}
	}

	public function dispatch($request) {
		$response = new Response($request);

		$file = parse_url($request->getURI(), PHP_URL_PATH);

		/* Static file handler */
		$file = realpath("{$this->path}/{$file}");

		if ($file === FALSE || strpos($file, $this->path) !== 0) {
			$response->setStatusCode(HTTP::STATUS_NOT_FOUND);
			$response->setContent("Duh, Not Found");
			return $response;
		}

		if (is_dir($file)) {
			$files = glob("{$file}/index.*");
			if (!is_array($files) || empty($files)) {
				$response->setStatusCode(HTTP::STATUS_FORBIDDEN);
				$response->setContent("Directory listing denied");
				return $response;
			}

			$file = reset($files);
		}

		if (($dotpos = strrpos($file, '.')) !== FALSE) {
			$extension = strtolower(substr($file, $dotpos + 1));

			if (isset(self::$contentTypeMap[$extension])) {
				$response->setHeader('Content-Type', self::$contentTypeMap[$extension]);
			}
		}

		$response->setContent(file_get_contents($file));

		return $response;
	}
}
