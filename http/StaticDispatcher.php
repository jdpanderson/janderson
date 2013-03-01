<?php
/**
 * This file defines the StaticDispatcher interface
 */
namespace janderson\net\http;

use \janderson\net\socket\server\Dispatchable;

/**
 * StaticDispatcher is a dispatcher for static content, i.e. serves files from a directory tree in the filesystem.
 */
class StaticDispatcher implements Dispatchable {
	protected $path;

	public function __construct($path = NULL) {
		$this->path = realpath(NULL);

		if (!$this->path) {
			throw new Exception("Path not found");
		}

		if (!is_dir($this->path)) {
			$this->path = dirname($this->path);
		}
	}

	public function dispatch($request) {
		$response = new Response($request);

		/* Static file handler */
		$file = realpath("{$this->path}/{$request->getURI()}");

		if ($file === FALSE || strpos($file, $this->path) !== 0) {
			$response->setStatusCode(HTTP::STATUS_NOT_FOUND);
			$response->setContent("Duh, Not Found");
		} elseif (is_dir($file)) {
			$response->setStatusCode(HTTP::STATUS_FORBIDDEN);
			$response->setContent("Directory listing denied");
		} else {
			$response->setContent(file_get_contents($file));
		}
		return $response;
	}
}
