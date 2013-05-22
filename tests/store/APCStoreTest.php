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
		parent::setUp();
	}
}