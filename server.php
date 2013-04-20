<?php
/**
 *
 */
namespace janderson\net;

spl_autoload_register(function($class_name) {
	$elements = explode('\\', $class_name);

	if ($elements[0] != 'janderson') {
		return FALSE;
	}

	require sprintf("%s/%s.php", __DIR__, implode('/', array_slice($elements, 2)));
});

use janderson\net\http\Handler as Handler;
use janderson\net\http\Dispatcher;
use janderson\net\http\StaticDispatcher;
use janderson\net\http\JSONRPCDispatcher;
use janderson\net\socket\Socket;
use janderson\net\socket\server\Server;
use janderson\net\socket\server\ForkingServer;

$options = getopt("a:hn:p:u:g:");

if (isset($options['h'])) {
	echo <<<HELP
Usage: {$argv[0]} [options]

Options:
  -h          Show this help
  -n <num>    Spawn up to <num> server processes.
  -a <addr>   Bind to address <addr>. (Default is all bind to all addresses.)
  -p <port>   Listen on port <port>. (Default is 80, or 8080 for non-root.)
  -u <euid>   Set the effective UID of the server processes. (root only.)
  -g <egid>   Set the effective GID of the server processes. (root only.)


HELP;
	exit(0);
}

$root = (posix_geteuid() === 0);
$port = isset($options['p']) ? $options['p'] : ($root ? 80 : 8080);
$addr = isset($options['a']) ? $options['a'] : Socket::ADDR_ANY;

/* Listen, then possibly switch the effective UID/GID */
$socket = new Socket();
$socket->setBlocking(FALSE);
$socket->listen(100, $addr, $port);

if ($root) {
	if (isset($options['u'])) {
		$uid = $options['u'];
		if (is_numeric($uid)) {
			$uid = (int)$uid;
		} else {
			if ($info = posix_getpwnam($uid)) {
				$uid = $info['uid'];
			}
		}

		if (is_int($uid)) {
			error_log("Switching to euid $uid");
			posix_seteuid($uid);
		}
	}
	if (isset($options['g'])) {
		$gid = $options['g'];
		if (is_numeric($gid)) {
			$gid = (int)$gid;
		} else {
			if ($info = posix_getgrnam($gid)) {
				$gid = $info['gid'];
			}
		}

		if (is_int($gid)) {
			error_log("Switching to egid $gid");
			posix_setegid($uid);
		}
	}
}

$processes = isset($options['n']) ? (int)$options['n'] : 1;
$processes = min(max($processes, 1), 1024); /* Reasonable bounds. */


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

$dispatcher = new Dispatcher(array(
	'/service/' => new JSONRPCDispatcher(array(new BlogService())),
	'/'         => new StaticDispatcher('/home/janderson/public_html/blog/')
));

//$svr = new /* Forking */Server($socket, 'janderson\\net\\socket\\server\\BaseHandler');
$svr = new /* Forking */Server($socket, 'janderson\\net\\http\\Handler');
$svr->run($processes);
