<?php

namespace janderson\tests\store;

class IPCStoreTest extends KeyValueStoreTest
{
	protected static $impl = "janderson\store\IPCStore";

	protected function tearDown()
	{
		if ($this->store) {
			$this->store->destroy();
		}
	}

	public function testBucketCollision()
	{
		/**
		 * In order to prevent variable collision, the IPC store uses buckets calculated using CRC-32. These two keys happen to collide.
		 */
		$vars = array(
			'plumless' => "test one",
			'buckeroo' => "test two"
		);

		foreach ($vars as $k => $v) {
			$this->assertEquals(TRUE, $this->store->set($k, $v));
			$this->assertEquals($v, $this->store->get($k));
		}

		foreach ($vars as $k => $v) {
			$this->assertEquals($v, $this->store->get($k));
			$this->assertEquals(TRUE, $this->store->delete($k));
			$this->assertEquals(FALSE, $this->store->get($k));
		}
	}

	public function testNamedKey()
	{
		$store = new \janderson\store\IPCStore(1000, "Non-Int Key"); /* If it doesn't throw an exception, we're good. */

		$this->assertTrue($store instanceof \janderson\store\IPCStore);
	}

	/**
	 * @expectedException janderson\store\StoreException
	 */
	public function testNonScalarKey()
	{
		$k = array('key');
		$v = "value";

		$this->store->set($k, $v);
	}

	/**
	 * @expectedException janderson\store\StoreException
	 */
	public function testConstructFailure()
	{
		$storeFail = new \janderson\store\IPCStore(-1);
	}
}