<?php

namespace janderson\log;

/**
* This is a simple Logger implementation that logs to the PHP error_log.
*
* This class implements the methods of Psr\Log\LoggerInterface without the implements keyword part, so we can have a placeholder for a logger without external dependencies.
*/
class ErrorLog implements LoggerInterface
{
	/**
	 * An array of possible log levels mapped to a numeric priority, lowest number means highest priority. Used to facilitate comparison.
	 *
	 * @var string[]
	 */
	protected static $levels = array(
		LogLevel::EMERGENCY => 1,
		LogLevel::ALERT     => 2,
		LogLevel::CRITICAL  => 3,
		LogLevel::ERROR     => 4,
		LogLevel::WARNING   => 5,
		LogLevel::NOTICE    => 6,
		LogLevel::INFO      => 7,
		LogLevel::DEBUG     => 8
	);

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function emergency($message, array $context = array()) { $this->log($level, $message); }

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function alert($message, array $context = array()) { $this->log($level, $message); }

	/**
	 * Critical conditions.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function critical($message, array $context = array()) { $this->log($level, $message); }

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function error($message, array $context = array()) { $this->log($level, $message); }

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function warning($message, array $context = array()) { $this->log($level, $message); }

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function notice($message, array $context = array()) { $this->log($level, $message); }

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function info($message, array $context = array()) { $this->log($level, $message); }

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function debug($message, array $context = array()) { $this->log($level, $message); }


	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 */
	public function log($level, $message, array $context = array())
	{
		/* If there's no such level, default to max to be safe. Perpetrator shoud fix it right quick. */
		if (!isset(self::$levels[$level])) {
			$level = LogLevel::EMERGENCY;
		}

		/* If the current level (say emergency - 1) is less than the message level (say debug - 8), don't log anything. */
		if ($this->level < self::$levels[$level]) {
			return;
		}

		/* If we have a message and context, build a map of replacements and use strtr to replace them efficiently. */
		if (!empty($context) && !empty($message)) {
			$replace = array();
			foreach ($context as $key => $val) {
				$replace['{' . $key . '}'] = $val;
			}
			$message = strtr($message, $replace);
		}

		error_log("$level: $message");
	}
}
