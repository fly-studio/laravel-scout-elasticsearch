<?php

namespace Addons\Elasticsearch\Scout;

use Addons\Elasticsearch\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Addons\Elasticsearch\Scout\Builder;
use Elasticsearch\Client as Elasticsearch;
use Illuminate\Pagination\LengthAwarePaginator;

class ElasticsearchEngine {

	/**
	 * The Elasticsearch client instance.
	 *
	 * @var \Elasticsearch\Client
	 */
	protected $elasticsearch;

	/**
	 * Create a new engine instance.
	 *
	 * @param  \Elasticsearch\Client  $elasticsearch
	 * @return void
	 */
	public function __construct(Elasticsearch $elasticsearch)
	{
		$this->elasticsearch = $elasticsearch;
	}

	/**
	 * Update the given model in the index.
	 *
	 * @param  Collection  $models
	 * @return void
	 */
	public function update($models)
	{
		$body = new Collection();

		$models->each(function ($model) use ($body) {
			$array = $model->toSearchableArray();

			if (empty($array)) {
				return;
			}

			$body->push([
				'index' => [
					'_index' => $model->searchableAs(),
					'_type' => '_doc',
					'_id' => $model->getKey(),
				],
			]);

			$body->push($array);
		});

		$this->elasticsearch->bulk([
			'refresh' => true,
			'body' => $body->all(),
		]);
	}

	/**
	 * Remove the given model from the index.
	 *
	 * @param  Collection  $models
	 * @return void
	 */
	public function delete($models)
	{
		$body = new Collection();

		$models->each(function ($model) use ($body) {
			$body->push([
				'delete' => [
					'_index' => $model->searchableAs(),
					'_type' => '_doc',
					'_id'  => $model->getKey(),
				],
			]);
		});

		$this->elasticsearch->bulk([
			'refresh' => true,
			'body' => $body->all(),
		]);
	}

	/**
	 * Perform the given search on the engine.
	 *
	 * @param  Builder  $query
	 * @return mixed
	 */
	public function execute(Builder $query)
	{
		return $this->performSearch($query, !is_null($query->limit) ? ['size' => $query->limit, 'from' => $query->offset] : []);
	}

	/**
	 * Perform the given search on the engine.
	 *
	 * @param  Builder  $query
	 * @return mixed
	 */
	public function count(Builder $query)
	{
		$result = $this->performCount($query);
		return isset($result['count']) ? $result['count'] : false;
	}

	/**
	 * Get the results of the query as a Collection of primary keys.
	 *
	 * @param  Addons\ElasticSearch\Scout\Builder  $builder
	 * @return \Illuminate\Support\Collection
	 */
	public function keys(Builder $builder)
	{
		return $this->getIds($this->execute($builder));
	}

	/**
	 * Get the results of the given query mapped onto models.
	 *
	 * @param  Addons\ElasticSearch\Scout\Builder  $builder
	 * @return \Illuminate\Database\Eloquent\Collection
	 */
	public function get(Builder $builder) : Collection
	{
		return $this->map($this->execute($builder), $builder->model);
	}

	/**
	 * Get the aggregations of the give aggs
	 * [warning] excute a search with each 'aggregations'
	 *
	 * @param  Addons\ElasticSearch\Scout\Builder  $builder
	 * @param  string  $key     eg: user_id_cardinality.value
	 * @return mixed
	 */
	public function aggregations(Builder $builder, $key = null)
	{
		$result = $this->execute($builder);
		return is_null($key) ? $result['aggregations'] : array_get($result['aggregations'], $key);
	}

	/**
	 * Perform the given search on the engine.
	 *
	 * @param  Builder  $query
	 * @param  int  $perPage
	 * @param  int  $page
	 * @return mixed
	 */
	public function paginate(Builder $builder, int $perPage = null, string $pageName = 'page', $page = null) : LengthAwarePaginator
	{
		$result = $this->performSearch($builder, [
			'size' => $perPage,
			'from' => (($page * $perPage) - $perPage),
		]);

		//$result['nbPages'] = (int) ceil($result['hits']['total'] / $perPage);

		return (new LengthAwarePaginator($this->map($result, $builder->model), $this->getTotalCount($result), $perPage, $page, [
			'path' => Paginator::resolveCurrentPath(),
			'pageName' => $pageName,
		]));
	}

	private function parseBody(Builder $builder)
	{
		$body = [];
		foreach(['query_string', 'match_all'/*, 'common', '', ''*/] as $var)
		{
			if (!is_null($builder->$var))
			{
				$body['query'][$var] = $builder->$var;
				break;
			}
		}
		empty($body['query']) && $body['query'] = $builder->bool->toArray();

		foreach(['_source', 'aggs', 'track_scores', 'stored_fields', 'docvalue_fields', 'highlight', 'rescore', 'explain', 'version', 'indices_boost', 'min_score', 'search_after'] as $var)
			!is_null($builder->$var) && $body[$var] = $builder->$var;

		return $body;
	}

	protected function performCount(Builder $builder, array $options = [])
	{
		$query = [
			'index' =>  $builder->model->searchableAs(),
			'type' => '_doc',
			'body' => $this->parseBody($builder),
		];
		return $this->elasticsearch->count($query);
	}

	/**
	 * Perform the given search on the engine.
	 * Parent::performSearch dont support elastic 5.x
	 *
	 * @param  Builder  $builder
	 * @param  array  $options
	 * @return mixed
	 */
	protected function performSearch(Builder $builder, array $options = [])
	{
		$query = [
			'index' =>  $builder->model->searchableAs(),
			'type' => '_doc',
			'body' => $this->parseBody($builder) + [
				'sort' => $builder->orders,
			],
		];

		if (array_key_exists('size', $options))
			$query['size'] = $options['size'];

		if (array_key_exists('from', $options))
			$query['from'] = $options['from'];

		if ($builder->callback) {
			call_user_func_array(
				$builder->callback,
				[
					$this->elasticsearch,
					&$query,
				]
			);
		}
		return $this->elasticsearch->search($query);
	}

	/**
	 * Map the given results to instances of the given model.
	 *
	 * @param  mixed  $results
	 * @param  \Illuminate\Database\Eloquent\Model  $model
	 * @return Collection
	 */
	protected function map($results, Model $model) : Collection
	{
		if (count($results['hits']) === 0)
			return Collection::make();

		return Collection::make($results['hits']['hits'])->map(function ($hit) {
				return $hit['_source'];
		})->setModelName($model);
	}

	/**
	 *
	 * Pluck and return the primary keys of the results.
	 *
	 * @param  mixed  $results
	 * @return \Illuminate\Support\Collection
	 */
	public function getIds($results) : array
	{

		return Collection::make($results['hits']['hits'])
						->map(function($v) {
							if (is_numeric($v['_id']))
								$id = $v['_id'];
							else
								list($id) = explode(':', $v['_id'], 2);
							return $id;
						})->all();
	}

	/**
	 * Get the total count from a raw result returned by the engine.
	 *
	 * @param  mixed  $results
	 * @return int
	 */
	public function getTotalCount($results)
	{
		return $results['hits']['total'];
	}
}
