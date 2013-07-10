<?php

namespace janderson\log;

/**
 * This is a simple Logger implementation that logs nothing.
 *
 * This class implements the methods of Psr\Log\LoggerInterface without the implements keyword part, so we can have a placeholder for a logger without external dependencies.
 */
class NullLogger implements LoggerInterface
{
	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function emergency($message, array $context = array()) {}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function alert($message, array $context = array()) {}

	/**
	 * Critical conditions.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function critical($message, array $context = array()) {}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function error($message, array $context = array()) {}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function warning($message, array $context = array()) {}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function notice($message, array $context = array()) {}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function info($message, array $context = array()) {}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function debug($message, array $context = array()) {}


	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 */
	public function log($level, $message, array $context = array()) {}
}
