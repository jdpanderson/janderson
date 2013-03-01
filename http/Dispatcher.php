<?php
/**
 * This file defines the Dispatcher interface
 */
namespace janderson\net\http;

use \janderson\net\socket\server\Dispatchable;
use \Closure;

/**
 * Dispatcher
 */
class Dispatcher implements Dispatchable {
	protected $prefixes = array();

	public function __construct($prefixes = array()) {
		$this->prefixes = $prefixes;
	}

	public function addPrefix($prefix, $dest) {
		if ($dest instanceof Dispatchable || $dest instanceof Closure) {
			$this->prefixes[$prefix] = $dest;
			return TRUE;
		}
		return FALSE;
	}

	public function dispatch($request) {
		/* A Trie would do this nicely, and would probably be faster. */
		foreach ($this->prefixes as $prefix => $dest) {
			if (strpos($request->getURI(), $prefix) === 0) {
				try {
					if ($dest instanceof Dispatchable) {
						$response = $dest->dispatch($request);
					} elseif ($dest instanceof Closure || (is_object($dest) && method_exists($dest, '__invoke'))) {
						$response = $dest($request);
					}

					if (!($response instanceof Response)) {
						$response = new Response($request);
						$response->setStatusCode(HTTP::STATUS_INTERNAL_SERVER_ERROR);
						$response->setContent("Internal Server Error: Request Processing Failure");
					}
				} catch (Exception $e) {
					$response = new Response($request);
					$response->setException($e);
				} catch (\Exception $e) {
					$response = new Response($request);
					$response->setStatusCode(HTTP::STATUS_INTERNAL_SERVER_ERROR);
					$response->setContent("Internal Server Error");
				}
			}
		}

		if (!isset($response)) {
			$response = new Response($request);
			$response->setStatusCode(HTTP::STATUS_INTERNAL_SERVER_ERROR);
			$response->setContent("Internal Server Error: No handler for URI");
		}

		return $response;
	}
}