<?php
/**
 * This is an example of how some of these components can be put together. This implements a limited HTTP server that can serve static files, PHP, JSON-RPC, and Websocket requests.
 */
namespace janderson;

spl_autoload_register(function($class_name) {
	$elements = explode('\\', $class_name);

	if ($elements[0] != 'janderson') {
		return FALSE;
	}

	$file = sprintf("%s/%s.php", __DIR__, implode('/', array_slice($elements, 1)));
	$result = include $file;

	if (!$result) {
		debug_print_backtrace();
		exit(1);
	}
});

use janderson\protocol\http\handler\Dispatcher;
use janderson\protocol\http\handler\StaticFileHandler;
use janderson\protocol\http\handler\PHPHandler;
use janderson\protocol\http\handler\JSONRPCHandler;
use janderson\protocol\websocket\WebsocketDispatcher;
use janderson\socket\Socket;
use janderson\socket\SocketException;
use janderson\socket\server\Server;
use janderson\socket\server\ForkingServer;
use janderson\configuration\ArgvConfig;
use janderson\configuration\JSONConfig;
use janderson\configuration\IniConfig;
use janderson\log\LogLevel;
use janderson\log\ErrorLog;
use janderson\misc\Posix;

/**
 * Allow configuration to be specified as argv or a config file.
 */
$config = new ArgvConfig(array(
	'c' => 'config',
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

/* Load/merge the config file. */
if ($file = $config->get('config')) {
	if (!file_exists($file)) {
		echo "File not found: $cfg\n";
		exit(1);
	}

	$success = FALSE;
	if (substr($file, -4) == ".ini") {
		$cfg = new IniConfig();
		$success = $cfg->load($file);
	} elseif (substr($file, -5) == ".json") {
		$cfg = new JSONConfig();
		$success = $cfg->load($file);
	}

	if (!$success) {
		echo "Config file format invalid or not supported.\n";
		exit(1);
	}

	foreach ($cfg->flatten() as $directive => $value) {
		$config->set($directive, $value);
	}
}

if ($config->get('help')) {
	echo <<<HELP
Usage: {$argv[0]} [options]

Options:
  -h, --help
  -c <cfg>,  --config <cfg>            Load configuration from file (JSON/ini)
  -H <cls>,  --server-handler <cls>    Use protocol handler class <cls>
  -n <num>,  --server-processes <num>  Spawn up to <num> server processes
  -a <addr>, --server-address <addr>   Bind to address <addr> (Default all)
  -p <port>, --server-port <port>      Listen on port <port> (Default 80*/8080)
  -u <uid>,  --server-euid <uid>       Set the EUID of the server process*
  -g <gid>,  --server-egid <egid>      Set the GUID of the server process*
  -l <cls>,  --logger-class <cls>      Use this PSR3-compatible logger class
  -L <lvl>,  --logger-level <lvl>      RFC5424 log level

* = Requires root

Accepted log levels: emergency, alert, critical, error, warning, notice, info,
					 and debug

Options for that apply when using Websocket or HTTP protocol handlers:

  Set HTTP request handlers (where N is a digit starting at 0):
	--http-N-php <0/1>     Execute PHP scripts (1) or not (0)
	--http-N-prefix <pfx>  HTTP path prefix (e.g. "/")
	--http-N-path <path>   Path used to serve files under the prefix

Options that apply when using Websocket the protocol handler:

  Set websocket protocol handlers (where N is a digit starting at 0):
	--ws-N-class <cls>  A protocol handler class name which will handle this
						websocket
	--ws-N-prefix <pfx> HTTP path prefix (path requested by websocket upgrade)


Example 1: Handle the Echo protocol on TCP port 7 (requires root)

  {$argv[0]} -p 7 -H 'janderson\\\\protocol\\\\handler\\\\EchoHandler'

Example 2: Simple HTTP server (requires root, drops privileges)

  {$argv[0]} -p 80 -H 'janderson\\\\protocol\\\\handler\\\\HTTPHandler' \
			 -u www-data -g www-data \
			 --http-0-prefix / --http-0-path /home/\$USER/public_html

Example 3: Serve HTTP w/ PHP, and an echo websocket handler on /echo

  {$argv[0]} -p 80 -H 'janderson\\\\protocol\\\\handler\\\\WebsocketHandler' \
			 --http-0-prefix / --http-0-path /var/www http-0-php 1 \
			 --ws-0-prefix /echo \
			 --ws-0-class 'janderson\\\\protocol\\\\handler\\\\EchoHandler'

Example 4: Example 3 with a config file

  {$argv[0]} -c server.ini

	OR

  {$argv[0]} -c server.json

  server.ini:

	[server]
	port = 80
	handler = "janderson\\\\protocol\\\\handler\\\\WebsocketHandler"

	[http]
	0.php = 1
	0.prefix = "/"
	0.path = "/var/www/"

	[ws]
	0.prefix = "/echo"
	0.class = "janderson\\\\protocol\\\\handler\\\\EchoHandler"

  server.json

	{
		"server": {
			"port": 80,
			"handler": "janderson\\\\protocol\\\\handler\\\\WebsocketHandler"
		},
		"http": [
			{ "php": true, "prefix": "/", "path": "/var/www/" }
		],
		"ws": [
			{ "prefix": "/echo", "class": "janderson\\\\protocol\\\\handker\\\\EchoHandler" }
		]
	}

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

try {
	$socket->listen(100, $addr, $port);
} catch (SocketException $e) {
	$logger->critical("Socket failed to listen (fatal): {message}", array('message' => $e->getMessage()));
	exit(1);
}

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

/**
 * HTTP requests are reasonably simple because they're stateless. They can be passed along to other request handlers based on prefix (or other things).
 */
$dispatcher = new Dispatcher();

$httpPrefixes = $config->get('http', array(array('prefix' => '/', 'path' => getcwd(), 'php' => TRUE)));

foreach ($httpPrefixes as $prefix) {
	if (empty($prefix['prefix'])) {
		$logger->warning("Prefix is missing a 'prefix' parameter. Skipped.");
		continue;
	}

	if (!empty($prefix['jsonrpc'])) {
		$logger->info("Handling requests for {prefix} with JSON-RPC handler", $prefix);
		$handler = new JSONRPCHandler($prefix['classes']);
	} elseif (empty($prefix['php']) && !empty($prefix['path'])) {
		$logger->info("Handling requests for {prefix} with static files from {path}", $prefix);
		$handler = new StaticFileHandler($prefix['path']);
	} elseif (!empty($prefix['path'])) {
		$logger->info("Handling requests for {prefix} with files from {path}", $prefix);
		$handler = new PHPHandler($prefix['path']);
	}
	$dispatcher->addPrefix($prefix['prefix'], $handler);
}

/**
 * Websocket requests are a little more involved because each websocket is a connected socket that is handled by a protocol handler, rather than a request handler. It deals in buffers and callbacks rather than simple request-response.
 *
 * First, set up a dispatcher that will create new protocol handler instances for known prefixes.
 * Second, create a callback that calls the dispatcher to return those instances.
 */
$wsDispatcher = new WebsocketDispatcher();
$wsPrefixes = $config->get('ws', array());
foreach ($wsPrefixes as $prefix) {
	if (!isset($prefix['prefix'], $prefix['class'])) {
		continue;
	}
	$logger->info("Handling Websocket requests for {prefix} with {class}", $prefix);
	$wsDispatcher->addPrefix($prefix['prefix'], $prefix['class']);
}

$wsFactory = function(&$buf, &$buflen, &$req, &$res) use($wsDispatcher) {
	$wsDispatcher->getProtocolHandler($buf, $buflen, $req, $res);
};

/**
 * The socket server accepts a callable that is expected to return a valid protocol handler. (A protocol handler factory.)
 *
 * The callable is expected to set up the handler, and any sub-instances or configuration that handler requires.
 *
 * For simple protocols, e.g. the EchoHandler, the callable only needs to return a new instance of the EchoHandler itself.
 * For more complex protocols, e.g. HTTP, the callable will need to do a lot more.
 */
$handler = $config->get('server.handler', 'janderson\\protocol\\handler\\WebsocketHandler');
$handlerFactory = function(&$buf, &$buflen) use ($handler, $dispatcher, $wsFactory) {
	$handlerInst = new $handler($buf, $buflen);
	$handlerInst->setHandler($dispatcher);
	$handlerInst->setProtocolHandlerFactory($wsFactory);
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
