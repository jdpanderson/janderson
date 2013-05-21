<?php

namespace janderson\tests\store;

use janderson\store\ArrayStore;
use janderson\store\APCStore;
use janderson\store\IPCStore;

class KeyValueStoreTest extends \PHPUnit_Framework_TestCase
{
	protected static $impl = "janderson\store\ArrayStore";
	protected $store;

	public function setUp()
	{
		$this->store = new static::$impl();
	}

	public function testKeyValueStoreBasics()
	{
		$k = "TestKey";
		$v = sprintf("Test Value: %s@%s", __FUNCTION__, time());

		/* Ensure the key doesn't initially exist. */
		$this->assertEquals(FALSE, $this->store->get($k));

		/* A delete on a non-existant key shouldn't succeed */
		$this->assertEquals(FALSE, $this->store->delete($k));

		/* Set it and check it. */
		$this->assertEquals(TRUE, $this->store->set($k, $v));
		$this->assertEquals($v, $this->store->get($k));

		/* Delete it and make sure it was deleted. */
		$this->assertEquals(TRUE, $this->store->delete($k));
		$this->assertEquals(FALSE, $this->store->get($k));

		/* Set it and make sure if it can be flushed that it is actually flushed. */
		$this->store->set($k, $v);
		if ($this->store->flush()) {
			$this->assertEquals(FALSE, $this->store->get($k));
		} else {
			/* Failure to flush is not an error if it returned the correct value (false). If that's the case, be a good citizen and delete the test value. */
			$this->store->delete($k);
		}
		$this->assertEquals(FALSE, $this->store->get($k));

		/**
		 * Throw in a few items and make sure each still makes sense.
		 */
		$vars = array();
		for ($i = 0; $i < 10; $i++) {
			$k = "test{$i}";
			$v = "value {$i}";
			$vars[$k] = $v;

			$this->store->set($k, $v);
			$this->assertEquals($v, $this->store->get($k));
		}

		foreach ($vars as $k => $v) {
			$this->assertEquals($v, $this->store->get($k));
			$this->store->delete($k);
			$this->assertEquals(FALSE, $this->store->get($k));
		}
	}
}