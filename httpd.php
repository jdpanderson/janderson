<?php
/**
 *
 */
namespace janderson\net;

require __DIR__ . "/socket/Socket.php";
require __DIR__ . "/socket/Exception.php";
require __DIR__ . "/socket/server/Handler.php";
require __DIR__ . "/socket/server/Dispatchable.php";
require __DIR__ . "/socket/server/Server.php";

require __DIR__ . "/http/HTTP.php";
require __DIR__ . "/http/Request.php";
require __DIR__ . "/http/Response.php";
require __DIR__ . "/http/Dispatcher.php";
require __DIR__ . "/http/StaticDispatcher.php";
require __DIR__ . "/http/JSONRPCDispatcher.php";
require __DIR__ . "/http/Handler.php";

use janderson\net\http\Handler as Handler;
use janderson\net\http\Dispatcher;
use janderson\net\http\StaticDispatcher;
use janderson\net\http\JSONRPCDispatcher;
use janderson\net\socket\Socket;
use janderson\net\socket\server\Server;

class TestService {
	public function test() {
		return "this is a test";
	}
}

$socket = new Handler();
$socket->setBlocking(FALSE);
$socket->listen(100, Socket::ADDR_ANY, 8080);

$dispatcher = new Dispatcher(array(
	'/service/' => new JSONRPCDispatcher(array(new TestService())),
	'/'         => new StaticDispatcher('/Users/janderson/public_html/blog/')
));

$svr = new Server($socket, $dispatcher);
$svr->run();