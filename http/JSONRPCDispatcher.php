<?php
/**
 * This file defines the JSONRPCDispatcher interface
 */
namespace janderson\net\http;

use \janderson\net\socket\server\Dispatchable;

/**
 * JSONRPCDispatcher
 */
class JSONRPCDispatcher implements Dispatchable {
	/**
	 * Errors specified in the JSON-RPC 2.0 spec.
	 */
	const ERR_PARSE            = -32700; /* Parse error: Invalid JSON was received by the server. */
	const ERR_INVALID_REQUEST  = -32600; /* Invalid Request: The JSON sent is not a valid Request object. */
	const ERR_METHOD_NOT_FOUND = -32601; /* Method not found: The method does not exist or is not available. */
	const ERR_INVALID_PARAMS   = -32602; /* Invalid params: Invalid method parameter(s). */
	const ERR_INTERNAL         = -32603; /* Internal error: Internal JSON-RPC error. */

	/**
	 * @var mixed[]
	 */
	protected $services = array();

	/**
	 * @var ReflectionObject[]
	 */
	protected $reflection = array();

	public function __construct($services = array()) {
		foreach ($services as $service) {
			$this->services[get_class($service)] = $service;
			$this->reflection[get_class($service)] = new ReflectionObject($service);
		}
	}

	public function dispatch($request) {
		$response = new Response($request);

		if ($request->getMethod() != HTTP::METHOD_POST || strtolower($request->getHeader('Content-Type')) != 'application/json') {
			$response->setStatusCode(HTTP::STATUS_BAD_REQUEST);
			$response->setContent("Bad Request");
			return $response;
		}

		/* Any responses from here on out are all JSON. */
		$response->setHeader('Content-Type', 'application/json');

		/* Figure out the service based on the path, if possible. */
		$path = parse_url($request->getURI(), PHP_URL_PATH);
		$service = basename($path);
		if (($dotpos = strrpos($service, '.')) !== FALSE) {
			$service = substr($service, 0, $dotpos);
		}

		if (!($obj = json_decode($request->getContent()))) {
			$response->setContent(json_encode(array(
				'error' => array('code' => self::ERR_PARSE, 'message' => 'Parse error')
			)));
			return $response;
		}

		if (is_array($obj)) {
			$results = array();
			foreach ($obj as $o) {
				$result = $this->callService($service, $o);
				if ($result !== NULL) {
					$results[] = $result;
				}
			}
			if (!empty($results)) {
				$response->setContent(json_encode($results));
			}
		} else {
			$result = $this->callService($service, $obj);
			if ($result !== NULL) {
				$response->setContent(json_encode($result));
			}
		}

		return $response;
	}

	/**
	 *
	 * @param mixed $service
	 * @param $reqObj
	 * @return array|null
	 */
	protected function callService($service, $reqObj) {
		$result = array(); /* json_encode encodes associative arrays as objects. */
		$service = $this->services[$service];

		if (isset($reqObj->id) && is_int($reqObj->id)) {
			$result['id'] = $reqObj->id;
		}

		/* JSON-RPC 2.0 is similar to JSON-RPC 1.0. The version string is one of the exceptions. */
		if (isset($reqObj->jsonrpc) && $reqObj->jsonrpc == '2.0') {
			$result['jsonrpc'] = '2.0';
		}

		/* Check if the service is known. Error out if it's not. */
		if (!isset($this->services[$service])) {
			$result['error'] = array('code' => self::ERR_METHOD_NOT_FOUND, 'message' => 'Method not found');
			return isset($reqObj->id) ? $result : NULL;
		}

		if (empty($reqObj->method)) {
			$result['error'] = array('code' => self::ERR_INVALID_REQUEST, 'message' => 'Invalid Request');
			return isset($reqObj->id) ? $result : NULL;
		}

		$service = $this->services[$service];
		$method = $reqObj->method;

		if (!is_callable(array($service, $method))) {
			$result['error'] = array('code' => self::ERR_METHOD_NOT_FOUND, 'message' => 'Method not found');
			return isset($reqObj->id) ? $result : NULL;
		}

		try {
			if (is_array($reqObj->params)) {
				$result['result'] = $this->reflection[$service]->getMethod($method)->invokeArgs($service, $reqObj->params);
			} elseif (is_object($reqObj->params)) {
				$result['result'] = $service->$method($reqObj->params);
			} else {
				$result['error'] = array('code' => self::ERR_INVALID_PARAMS, 'message' => 'Invalid Params');
			}
		} catch (\Exception $e) {
			$result['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
		}
		return isset($reqObj->id) ? $result : NULL;
	}
}