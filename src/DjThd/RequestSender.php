<?php

namespace DjThd;

class RequestSender
{
	protected $loop = null;
	protected $requestBuilder = null;
	protected $connector;
	protected $ip = '';
	protected $port = 80;
	protected $tls = false;
	protected $maxConcurrency = 1;
	protected $reqPerSocket = 50;

	public function __construct($loop, $requestBuilder, $connector, $ip, $port, $tls, $maxConcurrency, $reqPerSocket)
	{
		$this->loop = $loop;
		$this->requestBuilder = $requestBuilder;
		$this->connector = $connector;
		$this->ip = $ip;
		$this->port = $port;
		$this->tls = $tls;
		$this->maxConcurrency = $maxConcurrency;
		$this->concurrencyLimiter = new ConcurrencyLimiter($loop, $maxConcurrency);
		$this->reqPerSocket = $reqPerSocket;
	}

	public function run()
	{
		$this->concurrencyLimiter->run(function($data, $endCallback) {
			$this->connector->connect(($this->tls ? 'tls' : 'tcp') . '://' . $this->ip . ':' . $this->port)->then(function($connection) use ($endCallback) {
				$connection->on('end', function() use ($connection) {
					echo 'n';
					$connection->close();
				});
				$connection->on('error', function() use ($connection) {
					echo 'e';
					$connection->close();
				});
				$connection->on('close', $endCallback);
				$this->writeChunk($connection, $this->reqPerSocket);
				$this->concurrencyLimiter->enqueueItem(1);
				echo '.';
			})->otherwise($endCallback);
		});
		for($i = 0; $i <= $this->maxConcurrency; $i++) {
			$this->concurrencyLimiter->handleData($i);
		}
	}

	public function writeChunk($stream, $count)
	{
		$chunk = '';
		for($i = 0; $i < $count; $i++) {
			$chunk .= $this->requestBuilder->buildRequest();
		}
		$stream->end($chunk);
	}
}
