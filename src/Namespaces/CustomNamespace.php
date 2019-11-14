<?php

namespace Addons\Elasticsearch\Namespaces;

use Elasticsearch\Transport;
use Elasticsearch\Namespaces\AbstractNamespace;
use Elasticsearch\Serializers\SerializerInterface;

class CustomNamespace extends AbstractNamespace {

	public function get(string $uri, array $params = [], string $body = null, array $options = [])
	{
		return $this->execute('GET', $uri, $params, $body, $options);
	}

	public function post(string $uri, array $params = [], string $body = null, array $options = [])
	{
		return $this->execute('POST', $uri, $params, $body, $options);
	}

	public function delete(string $uri, array $params = [], string $body = null, array $options = [])
	{
		return $this->execute('DELETE', $uri, $params, $body, $options);
	}

	public function put(string $uri, array $params = [], string $body = null, array $options = [])
	{
		return $this->execute('PUT', $uri, $params, $body, $options);
	}

	protected function execute(string $method, string $uri, array $params = [], string $body = null, array $options = [])
	{
		if (strpos($uri, '/') !== 0) $uri = '/'.$uri;

		$response = $this->transport->performRequest(
			$method,
			$uri,
			$params,
			$body,
			$options
		);

		return $this->transport->resultOrFuture($response, $options);
	}

}
