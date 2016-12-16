<?php
namespace Addons\Elasticsearch;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder as Elasticsearch;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\ElasticSearchHandler;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\RotatingFileHandler;

/**
 * Class ServiceProvider
 *
 * @package Cviebrock\LaravelElasticsearch
 */
class ServiceProvider extends BaseServiceProvider
{

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$configPath = realpath(__DIR__ . '/../config/elasticsearch.php');
		$this->publishes([
			$configPath => config_path('elasticsearch.php'),
		]);

		//the scout/ElasticsearchEngine supported elasticsearch 2.x, fix it
		app(\Laravel\Scout\EngineManager::class)->extend('elasticsearch', function(){
			return new AdvancedElasticsearchEngine(
				Elasticsearch::fromConfig(config('scout.elasticsearch.config')),
				config('scout.elasticsearch.index')
			);
		});

		$type = app('elasticsearch')->getConfig('index');
		$index = 'logstash-'.$type;
		switch (app('elasticsearch')->getConfig('logstashDriver'))
		{
			case 'file':
				$handler = new RotatingFileHandler(
		            storage_path('/logs/logstash.log'),
		            config('app.log_max_files', 5),
		            config('app.log_level', 'debug')
		        );
	            $handler->setFormatter(new LogstashFormatter($type, null, null, 'ctxt_', LogstashFormatter::V1));
				break;
			case 'redis':
				$handler = new RedisHandler(app('redis')->connection(), $index);
	            $handler->setFormatter(new LogstashFormatter($type, null, null, 'ctxt_', LogstashFormatter::V1));
				break;
			case 'elasticsearch':
				// laravel's log write to Logstash
				$config = [
					'index' => $index,
					'type' => $type
				];
				$handler = new ElasticSearchHandler(app(Client::class), $config);
				break;
				
		}
		!empty($handler) && app('log')->getMonolog()->pushHandler($handler);
		
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$app = $this->app;

		$this->mergeConfigFrom(__DIR__ . '/../config/elasticsearch.php', 'elasticsearch');
		config('elasticsearch.connections.default.logObject') === 'monolog' && config(['elasticsearch.connections.default.logObject' => $app->make('log')]);
		config('scout.elasticsearch.config.logger') === 'monolog' && config(['scout.elasticsearch.config.logger' => $app->make('log')]);

		$app->singleton('elasticsearch.factory', function($app) {
			return new Factory();
		});

		$app->singleton('elasticsearch', function($app) {
			return new Manager($app, $app['elasticsearch.factory']);
		});

		$app->singleton(Client::class, function($app) {
			return $app['elasticsearch']->connection();
		});
	}
}
