<?php

namespace janderson\tests\store;

class APCStoreTest extends KeyValueStoreTest
{
	protected static $impl = "janderson\store\APCStore";

	public function setUp()
	{
		if (!extension_loaded('apc')) {
			$this->markTestSkipped("APC not available");
		}

		if (ini_get('apc.enable_cli') != "1") {
			$this->markTestSkipped("APC not enabled. Please set apc.enable_cli = 1");
		}

		parent::setUp();
	}

	public function testIncDec()
	{
		$key = "test";
		$this->store->set($key, 0);
		$this->assertEquals(1, $this->store->inc($key));
		$this->assertEquals(0, $this->store->dec($key));
	}
}