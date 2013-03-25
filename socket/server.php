<?php
/**
 *
 */
namespace janderson\net;

require __DIR__ . "/Buffer.php";
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

use janderson\net\http\Handler;
use janderson\net\http\Dispatcher;
use janderson\net\http\StaticDispatcher;
use janderson\net\http\JSONRPCDispatcher;
use janderson\net\socket\Socket;
use janderson\net\socket\server\Server;

class BlogEntry {
	public $author = "J. Anderson";
	public $title = "Test Title";
	public $date = "2013-01-01 00:00:00";
	public $text = "Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";
}

class BlogService {
	public function getRecentEntries() {
		return array(
			new BlogEntry(),
			new BlogEntry(),
			new BlogEntry()
		);
	}
}

$socket = new Handler();
$socket->setBlocking(FALSE);
$socket->listen(100, Socket::ADDR_ANY, 8080);

$dispatcher = new Dispatcher(array(
	'/service/' => new JSONRPCDispatcher(array(new BlogService())),
	'/'         => new StaticDispatcher('/home/janderson/public_html/blog/')
));

$svr = new Server($socket, $dispatcher);
$svr->run();
