<?php
namespace janderson\misc;

/**
 * A class representing a channel through which control information and file descriptors can be sent.
 *
 * The intended use is to pass file descriptors between a parent and any forked children:
 * <code>
 * $ch = new FDChannel();
 * $pid = pcntl_fork();
 * if ($pid === 0) {
 *     sleep(1);
 *     var_dump($ctrl->recv());
 * } elseif ($pid > 0) {
 *     $ctrl->send([STDOUT]);
 * }
 * </code>
 *
 * This code uses a linux mechanism called "ancillary data", and is not likely portable. If you know otherwise, please let me know.
 */
class ControlChannel
{
	const TEMPNAM_PREFIX = "CtlCh";
	const BUFFER_SIZE_DEFAULT = 2048;
	const BUFFER_SIZE_MINIMUM = 512;

	/**
	 * The unix domain socket used to pass around file descriptors.
	 *
	 * @var Resource
	 */
	private $sock;

	/**
	 * The path to the unix domain socket file descriptor.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * The size to be allocated for the receive buffer to allocate, in bytes.
	 *
	 * @var int
	 */
	private $bufsz;

	/**
	 * Initialize the file descriptor passing channel: create a unix domain socket.
	 *
	 * @param int $bufsz The size of buffer to be created when receiving data, in bytes. Total data sent via the control channel must never be larger than this.
	 */
	public function __construct($bufsz = self::BUFFER_SIZE_DEFAULT)
	{
		$this->bufsz = max((int)$bufsz, self::BUFFER_SIZE_MINIMUM);
		$this->sock = socket_create(AF_UNIX, SOCK_DGRAM, 0);
		$this->path = tempnam(NULL, self::TEMPNAM_PREFIX);
		unlink($this->path);
		socket_bind($this->sock, $this->path);
		socket_set_nonblock($this->sock);
	}

	/**
	 * Clean up the internal unix domain socket 
	 */
	public function __destruct()
	{
		@socket_close($this->sock);
		@unlink($this->path);
	}

	/**
	 * Send an array of sockets.
	 *
	 * @param string $iov A control message; A string or encoded structure.
	 * @param Resource[] $resource An array of file descriptors.
	 * @return bool Returns true if the file descriptors were sent successfully.
	 *
	 * Note: the solitary message "__DESTRUCT__" is reserved internally to mean destroy the control channel.
	 */
	public function send($iov = '', array $data = [])
	{
		/* This undocumented structure is similar to struct msghdr. @see recv(2) */
		$msghdr = [
			"name" => ["path" => $this->path],
			"iov" => [(string)$iov]
		];

		/* If we have data, assume we're sending resources so send it as ancillary data. */
		if (!empty($data)) {
			$msghdr['control'] = [[
				"level" => SOL_SOCKET,
				"type" => SCM_RIGHTS,
				"data" => $data
			]];
		}

		$result = @socket_sendmsg($this->sock, $msghdr, 0 /* Flags */);

		return $result !== FALSE;
	}

	/**
	 * Receive an array of sockets. 4
	 *
	 * @return [string[], Resource[]] An array of file descriptors that were passed to the send method, or false on failure.
	 */
	public function recv()
	{
		$data = [
			"name" => [],
			"buffer_size" => $this->bufsz,
			"controllen" => socket_cmsg_space(SOL_SOCKET, SCM_RIGHTS, 3)
		];

		$result = @socket_recvmsg($this->sock, $data, 0);

		if ($result === FALSE) {
			return FALSE;
		}

		return [
			isset($data['iov'][0]) ? $data['iov'][0] : "",
			isset($data['control'][0]['data']) ? $data['control'][0]['data'] : []
		];
	}
}


