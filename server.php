<?php
/**
 *
 */
namespace janderson;

spl_autoload_register(function($class_name) {
	$elements = explode('\\', $class_name);

	if ($elements[0] != 'janderson') {
		return FALSE;
	}

	$file = sprintf("%s/%s.php", __DIR__, implode('/', array_slice($elements, 1)));
	require $file;
});

use janderson\protocol\http\Dispatcher;
use janderson\protocol\http\StaticDispatcher;
use janderson\protocol\http\PHPDispatcher;
use janderson\protocol\http\JSONRPCDispatcher;
use janderson\socket\Socket;
use janderson\socket\server\Server;
use janderson\socket\server\ForkingServer;
use janderson\configuration\ArgvConfig;
use janderson\log\LogLevel;
use janderson\log\ErrorLog;

/**
 * Allow configuration to be specified as argv or a config file.
 */
$config = new ArgvConfig(array(
	'h' => 'help',
	'n' => 'server.processes',
	'H' => 'server.handler',
	'a' => 'server.address',
	'p' => 'server.port',
	'u' => 'server.euid',
	'g' => 'server.guid',
	'l' => 'logger.class',
	'L' => 'logger.level',
));
$config->load();

if ($config->get('help')) {
	echo <<<HELP
Usage: {$argv[0]} [options]

Options:
  -h           Show this help
  -H <handler> Use protocol handler class <handler>
  -n <num>     Spawn up to <num> server processes.
  -a <addr>    Bind to address <addr>. (Default is all bind to all addresses.)
  -p <port>    Listen on port <port>. (Default is 80, or 8080 for non-root.)
  -u <euid>    Set the effective UID of the server processes. (root only.)
  -g <egid>    Set the effective GID of the server processes. (root only.)
  -l <logger>  A PSR3-compatible logger class. Defaults to janderson\log\ErrorLog
  -L <level>   The level of messages to log. Levels are RFC5424 log levels.

Equivalent long form options:
  h: --help 
  H: --server-handler <handler>
  n: --server-processes <num>
  a: --server-address <addr>
  p: --server-port <port>
  u: --server-euid <euid>
  g: --server-egid <egid>
  l: --logger-class <logger>
  L: --logger-level <level>

Accepted log levels: emergency, alert, critical, error, warning, notice, info, and debug


HELP;
	exit(0);
}

$root = (posix_geteuid() === 0);
$port = $config->get('server.port', $root ? 80 : 8080);
$addr = $config->get('server.address', Socket::ADDR_ANY);
$handler = $config->get('server.handler', 'janderson\\protocol\\handler\\HTTPHandler');

$logger = $config->get('logger.class', 'janderson\\log\\ErrorLog');
$level = $config->get('logger.level', LogLevel::WARNING);
$logger = new $logger($level);
$logger->debug("Logger created. Level set to {level}", array('level' => $level));

/* Listen, then possibly switch the effective UID/GID */
$socket = new Socket();
$socket->setBlocking(FALSE);
$socket->listen(100, $addr, $port);

if ($root) {
	if ($config->get('server.euid')) {
		$uid = $config->get('server.euid');
		if (is_numeric($uid)) {
			$uid = (int)$uid;
		} else {
			if ($info = posix_getpwnam($uid)) {
				$uid = $info['uid'];
			}
		}

		if (is_int($uid)) {
			$logger->info("Switching to euid {euid}", array('euid' => $uid));
			posix_seteuid($uid);
		}
	}
	if ($config->get('server.egid')) {
		$gid = $config->get('server.egid');
		if (is_numeric($gid)) {
			$gid = (int)$gid;
		} else {
			if ($info = posix_getgrnam($gid)) {
				$gid = $info['gid'];
			}
		}

		if (is_int($gid)) {
			$logger->info("Switching to egid {egid}", array('egid' => $gid));
			posix_setegid($gid);
		}
	}
}

$processes = $config->get('server.processes', 1);
$processes = min(max($processes, 1), 1024); /* Clamp to reasonable bounds. */


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
	'/'         => new PHPDispatcher('/home/janderson/public_html/blog/')
));

$handlerFactory = function(&$buf, &$buflen, $params) use ($handler, $dispatcher) {
	$handlerInst = new $handler($buf, $buflen, $params);
	$handlerInst->setDispatcher($dispatcher);
	return $handlerInst;
};

if ($processes === 1) {
	$svr = new Server($socket, $handlerFactory);
	$svr->setLogger($logger);
	$svr->run();
} else {
	$svr = new ForkingServer($socket, $handlerFactory);
	$svr->setLogger($logger);
	$svr->run($processes);
}
