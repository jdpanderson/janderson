<?php

namespace janderson\misc;

/**
 * Posix functionality. Currently convenience wrappers around posix functions.
 */
class Posix
{
	/**
	 * The UID of root
	 */
	const UID_ROOT = 0;

	/**
	 * Get the effective user ID of the currently running process.
	 *
	 * @return int The effective user ID.
	 */
	public static function getEUID()
	{
		return posix_geteuid();
	}

	/**
	 * Set the effective user ID; Switch the currently running effective user.
	 *
	 * @param mixed $uid The numeric user ID or string user name
	 * @return bool True if setegid was successful.
	 */
	public static function setEUID($uid)
	{
		if (is_numeric($uid)) {
			$uid = (int)$uid;
		} else {
			if ($info = posix_getpwnam($uid)) {
				$uid = $info['uid'];
			}
		}

		if (is_int($uid)) {
			return posix_seteuid($uid);
		}

		return FALSE;
	}

	/**
	 * Set the effective group ID; Switch the effective group of the currently running user.
	 *
	 * @param mixed $gid The numeric group ID or string group name
	 * @return bool True if setegid was successful.
	 */
	public static function setEGID($gid)
	{
		if (is_numeric($gid)) {
			$gid = (int)$gid;
		} else {
			if ($info = posix_getgrnam($gid)) {
				$gid = $info['gid'];
			}
		}

		if (is_int($gid)) {
			return posix_setegid($gid);
		}

		return FALSE;
	}
}