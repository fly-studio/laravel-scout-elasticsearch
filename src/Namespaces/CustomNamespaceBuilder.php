<?php

namespace Addons\Elasticsearch\Namespaces;

use Elasticsearch\Transport;
use Elasticsearch\Serializers\SerializerInterface;
use Elasticsearch\Namespaces\NamespaceBuilderInterface;

class CustomNamespaceBuilder implements NamespaceBuilderInterface {

	public function getName(): string
	{
		return 'custom';
	}

	public function getObject(Transport $transport, SerializerInterface $serializer)
	{
		return new CustomNamespace($transport, function(){
			
		});
	}

}
