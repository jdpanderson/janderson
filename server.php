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
use janderson\protocol\http\StaticFileHandler;
use janderson\protocol\http\PHPHandler;
use janderson\protocol\http\JSONRPCHandler;
use janderson\socket\Socket;
use janderson\socket\server\Server;
use janderson\socket\server\ForkingServer;
use janderson\configuration\ArgvConfig;
use janderson\log\LogLevel;
use janderson\log\ErrorLog;
use janderson\misc\Posix;

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
	// XXX FIXME: ArgvConfig should have a helper to generate commandline help text.
}

/* Determine address and port from config, or reasonable defaults for the current UID */
$root = (Posix::getEUID() == Posix::UID_ROOT);
$port = $config->get('server.port', $root ? 80 : 8080);
$addr = $config->get('server.address', Socket::ADDR_ANY);

/* Set up the logger based on config, or a basic logger by default. */
$logger = $config->get('logger.class', 'janderson\\log\\ErrorLog');
$level = $config->get('logger.level', LogLevel::WARNING);
$logger = new $logger($level);
$logger->debug("Logger created. Level set to {level}", array('level' => $level));

/* Listen, then possibly switch the effective UID/GID */
$socket = new Socket();
$socket->setBlocking(FALSE);
$socket->listen(100, $addr, $port);

/* Handle EUID/EGID switching, if requested. */
if ($root) {
	$uid = $config->get('server.euid');
	if ($uid) {
		if (Posix::setEUID($uid)) {
			$logger->info("Switching to euid {euid}", array('euid' => $uid));
		} else {
			$logger->warning("Failed to switch to euid {euid}", array('euid' => $uid));
		}
	}

	$gid = $config->get('server.egid');
	if ($gid) {
		if (Posix::setEGID($gid)) {
			$logger->info("Switching to egid {egid}", array('egid' => $gid));
		} else {
			$logger->warning("Failed to switch to egid {egid}", array('egid' => $gid));
		}
	}
}

$dispatcher = new Dispatcher(array(
	'/' => new PHPHandler('/home/janderson/public_html/blog/')
));

$wsDispatcher = new WebsocketDispatcher(
	array(
		'/echo1/' => function(&$buf, &$buflen) { return new janderson\protocol\handler\EchoHandler($buf, $buflen); },
		'/echo2/' => 'janderson\\protocol\\handler\\EchoHandler'
	)
);

/**
 * The socket server accepts a callable that is expected to return a valid protocol handler. (A protocol handler factory.)
 *
 * The callable is expected to set up the handler, and any sub-instances or configuration that handler requires.
 *
 * For simple protocols, e.g. the EchoHandler, the callable only needs to return a new instance of the EchoHandler itself.
 * For more complex protocols, e.g. HTTP, the callable will need to do a lot more.
 */
$handler = $config->get('server.handler', 'janderson\\protocol\\handler\\HTTPHandler');
$handlerFactory = function(&$buf, &$buflen, $params) use ($handler, $dispatcher) {
	$handlerInst = new $handler($buf, $buflen, $params);
	$handlerInst->setHandler($dispatcher);
	return $handlerInst;
};

$processes = $config->get('server.processes', 1);
$processes = min(max($processes, 1), 1024); /* Clamp to reasonable bounds. */
if ($processes <= 1) {
	$svr = new Server($socket, $handlerFactory);
	$svr->setLogger($logger);
	$svr->run();
} else {
	$svr = new ForkingServer($socket, $handlerFactory);
	$svr->setLogger($logger);
	$svr->run($processes);
}
