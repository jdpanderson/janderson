<?php

namespace janderson\misc;

/**
 * An interface for things that should be cleaned up manually.
 *
 * For example, several processes may hold a resource for shared memory but only the last one should destroy the shared memory.
 */
interface Destroyable
{
	/**
	 * Execution of this method should destroy any resources associated with the class, and call destroy() on any Destroyable objects it holds.
	 */
	public function destroy();
}
