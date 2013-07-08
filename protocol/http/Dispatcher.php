<?php
/**
 * This file defines the Dispatcher interface
 */
namespace janderson\protocol\http;

use \Closure;

/**
 * Dispatches an HTTP request to a prefix-specific destination, usually a callback or another dispatcher.
 *
 * The destination must be either Dispatchable or a callback.
 *
 * The callback must accept parameters ($request, &$response)
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

	/**
	 * @param Request $request
	 * @return Response
	 */
	public function dispatch(&$request, &$response) {
		/* A Trie would do this nicely, and would probably be faster. */
		foreach ($this->prefixes as $prefix => $dest) {
			if (strpos($request->getURI(), $prefix) === 0) {
				try {
					if ($dest instanceof Dispatchable) {
						$dest->dispatch($request, $response);
					} elseif ($dest instanceof Closure || (is_object($dest) && method_exists($dest, '__invoke'))) {
						$dest($request, $response);
					} elseif (is_callable($dest)) {
						call_user_func($dest, $request, $response);
					}
				} catch (HTTPException $e) {
					$response->setException($e);
				} catch (\Exception $e) {
					$response->setStatusCode(HTTP::STATUS_INTERNAL_SERVER_ERROR);
					$response->setContent("Internal Server Error");
				}
				break;
			}
		}

		if (!($response instanceof Response)) {
			$response = new Response($request);
			$response->setStatusCode(HTTP::STATUS_INTERNAL_SERVER_ERROR);
			$response->setContent("Internal Server Error: Request Processing Failure");
		}

		return $response;
	}
}
