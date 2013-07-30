<?php
/**
 * This file defines the Dispatcher class, used to dispatch HTTP requests to RequestHandlers based on prefixes.
 */
namespace janderson\protocol\http\handler;

use janderson\protocol\http\RequestHandler;

/**
 * A request handler that dispatches an HTTP request to a prefix-specific destination, usually a callback or another ResponseHandler.
 *
 * The destination must be either RequestHandler (PHP 5.3+) or a callback (PHP 5.4+).
 *
 * The callback must accept parameters ($request, &$response). For example:
 * <code>
 * function(&$request, &$response) { $response->setContent("Hello, world!"); };
 * </code>
 */
class Dispatcher implements RequestHandler {
	/**
	 * An array of prefixes mapping to their RequestHandler or callable handlers.
	 *
	 * @var RequestHandler[]
	 */
	protected $prefixes = array();

	/**
	 * @param RequestHandler[] $prefixes
	 */
	public function __construct($prefixes = array()) {
		foreach ($prefixes as $prefix => $dest) {
			$this->addPrefix($prefix, $dest);
		}
	}

	/**
	 * Add a prefix and a callable/handler destination.
	 *
	 * @param string $prefix The first portion of the URL's path. 
	 * @param 
	 */
	public function addPrefix($prefix, $handler)
	{
		if ($handler instanceof RequestHandler || (PHP_VERSION_ID >= 50400 && is_callable($handler))) {
			$this->prefixes[$prefix] = $handler;
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * @param Request $request
	 * @return Response
	 */
	public function handle(Request &$request, Response &$response) {
		/* A Trie might do this better, and would probably be faster. */
		foreach ($this->prefixes as $prefix => $dest) {
			if (strpos($request->getURI(), $prefix) === 0) {
				try {
					if ($dest instanceof RequestHandler) {
						$dest->handle($request, $response);
					} elseif (PHP_VERSION_ID >= 50400 && is_callable($dest)) {
						$dest($request, $response);
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
