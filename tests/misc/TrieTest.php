<?php

namespace janderson\tests\misc;

use janderson\misc\Trie;

class TrieTest extends \PHPUnit_Framework_TestCase
{
	public function testExistence()
	{
		$t = new Trie();
		$t->add('test123');

		$this->assertFalse($t->get('test12'), 'Non-existant key should return false');
		$this->assertFalse($t->get('test1234'), 'Non-existant key should return false');
		$this->assertTrue($t->get('test123'), 'Existing key should return true when found.');
	}

	public function testValueFetch()
	{
		$t = new Trie();
		$t->add('test123', "test value");

		$this->assertEquals($t->get('test123'), "test value");
		$this->assertFalse($t->get('test12'));
		$this->assertFalse($t->get('test1234'));
	}

	public function testFetchPrefix()
	{
		$longest = $pfxkey = NULL;

		$t = new Trie();
		$t->add('test123', "test value");

		$this->assertFalse($t->get('test1234', $longest));
		$this->assertEquals("test value", $longest[1]);
		$this->assertEquals("test123", $longest[0]);
		$this->assertEquals("test value", $t->prefix("test1234", $pfxkey));
		$this->assertEquals("test123", $pfxkey);
		$this->assertFalse($t->prefix("abc123"));
	}
}
