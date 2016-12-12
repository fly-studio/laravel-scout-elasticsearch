<?php
namespace Addons\Elasticsearch;

use Illuminate\Contracts\Foundation\Application;


/**
 * Class Manager
 *
 * @package Cviebrock\LaravelElasticsearch
 */
class Manager
{

    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The Elasticsearch connection factory instance.
     *
     * @var Factory
     */
    protected $factory;

    /**
     * The active connection instances.
     *
     * @var array
     */
    protected $connections = [];

    /**
     * @param Application $app
     * @param Factory $factory
     */
    public function __construct(Application $app, Factory $factory)
    {
        $this->app = $app;
        $this->factory = $factory;
    }

    /**
     * Retrieve or build the named connection.
     *
     * @param null $connectionName
     * @return \Elasticsearch\Client|mixed
     */
    public function connection($connectionName = null)
    {
        $connectionName = $connectionName ?: $this->getDefaultConnection();

        if (!isset($this->connections[$connectionName])) {
            $client = $this->makeConnection($connectionName);

            $this->connections[$connectionName] = $client;
        }

        return $this->connections[$connectionName];
    }

    /**
     * Get the default connection.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return config('elasticsearch.defaultConnection');
    }

    /**
     * Set the default connection.
     *
     * @param string $connection
     */
    public function setDefaultConnection($connection)
    {
        return config(['elasticsearch.defaultConnection' => $connection]);
    }

    /**
     * Make a new connection.
     *
     * @param $connectionName
     * @return \Elasticsearch\Client|mixed
     */
    protected function makeConnection($connectionName)
    {
        $config = $this->getConnectionConfig($connectionName);

        return $this->factory->make($config);
    }

    /**
     * Get the configuration for a named connection.
     *
     * @param $connectionName
     * @return mixed
     */
    public function getConnectionConfig($connectionName)
    {
        $config = config('elasticsearch.connections.'.$connectionName);
        
        if (is_null($config))
            throw new \InvalidArgumentException("Elasticsearch connection [$name] not configured.");

        return $config;
    }

    public function getConfig($config, $connectionName = null)
    {
        empty($connectionName) && $connectionName = $this->getDefaultConnection();
        return config('elasticsearch.connections.'.$connectionName.'.'.$config);
    }

    /**
     * Return all of the created connections.
     *
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * Dynamically pass methods to the default connection.
     *
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection(), $method], $parameters);
    }
}
