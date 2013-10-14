<?php

namespace janderson\tests\lock;

use janderson\lock\APCLock;

/**
 * Wrapper around APCLock to expose some internals.
 */
class APCLockSpy extends APCLock
{
	public static $initFail = FALSE;
	public static $storeFail = FALSE;
	public $incFail = FALSE;
	public $decFail = FALSE;


	protected function init()
	{
		if (self::$initFail) {
			self::$initFail = FALSE;
			return FALSE;
		}

		return parent::init();
	}

	public function inc()
	{
		if ($this->incFail) {
			$this->incFail = FALSE;
			return FALSE;
		}

		return parent::inc();
	}

	public function dec()
	{
		if ($this->decFail) {
			$this->decFail = FALSE;
			return FALSE;
		}

		return parent::dec();
	}

	public function store($value)
	{
		if (self::$storeFail) {
			self::$storeFail = FALSE;
			return FALSE;
		}

		return parent::store($value);
	}

	public function get()
	{
		return apc_fetch($this->key);
	}

}
