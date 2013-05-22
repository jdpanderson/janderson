<?php

namespace janderson\tests\lock;

class APCLockTest extends LockTest
{
	protected static $impl = "janderson\lock\APCLock";
}