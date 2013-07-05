<?php

namespace janderson\tests\configuration;

use janderson\configuration\Argv;

class ArgvTest extends \PHPUnit_Framework_TestCase
{
	public function testLoad()
	{
		$GLOBALS['argv'] = array(NULL, "--opt-with-value", "foo", "nonopt", "--opt-without-value", "--opt-with-index-0", "bar", "--opt-with-equals=baz", "-h", "-o", "o-value", "-p", "p-value", "-qq-value", "--repeated-option", "0", "--repeated-option", "1", "non-option", "non-option-2", "--", "nonopt3", "--non-opt-4");
		$cfg = new Argv(array("o" => "opt-short"));
		$cfg->load();

		/* Make sure options were set correctly. */
		$this->assertEquals("foo", $cfg['opt.with.value']);
		$this->assertEquals(TRUE, $cfg['opt.without.value']);
		$this->assertEquals("bar", $cfg['opt.with.index.0']);
		$this->assertEquals("baz", $cfg['opt.with.equals']);
		$this->assertEquals(array("0", "1"), $cfg['repeated.option']);
		$this->assertEquals(TRUE, $cfg['h']);
		$this->assertEquals("o-value", $cfg['opt.short']);
		$this->assertEquals("p-value", $cfg['p']);
		$this->assertEquals("q-value", $cfg['q']);

		/* Make sure that non-options are added to the conf array's numeric indexes in order. */
		$this->assertEquals("nonopt", $cfg[0]);
		$this->assertEquals("non-option", $cfg[1]);
		$this->assertEquals("non-option-2", $cfg[2]);
		$this->assertEquals("nonopt3", $cfg[3]);
		$this->assertEquals("--non-opt-4", $cfg[4]);
	}

	public function testSave()
	{
		$cfg = new Argv();
		$this->assertFalse($cfg->save()); /* Saving not possible. Should fail. */
	}
}
