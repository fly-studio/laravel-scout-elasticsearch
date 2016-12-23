<?php
namespace Addons\Elasticsearch\Scout;

use Laravel\Scout\Engines\ElasticsearchEngine as BaseElasticsearchEngine;
use Addons\Elasticsearch\Scout\Builder;
class ElasticsearchEngine extends BaseElasticsearchEngine {

	/**
     * Perform the given search on the engine.
     *
     * @param  Builder  $query
     * @return mixed
     */
    public function count(Builder $query)
    {
        $result = $this->performSearch($query, [
            'filters' => $this->filters($query),
        ]);
        return isset($result['count']) ? $result['count'] : false;
    }

	protected function makeMatches($query, $filters)
	{
		$matches = [];
		if (!empty($query))
			$matches[] = [
				'match' => [
					'_all' => [
						'query' => $query,
						'fuzziness' => 1
					]
				]
			];

		if (!empty( $filters )) {
			foreach ( $filters as $field => $value) {

				if (strpos($field, ',') !== false)
				{
					$matches[] = [
						'multi_match' => [
							'fields' => explode(',', $field),
							'query' => $value
						]
					];
				}
				else if(is_string($value)) {
					$matches[] = [
						'match' => [
							$field => [
								'query' => $value,
								'operator' => 'and',
							],
						],
					];
				} elseif (is_array($value)) {
					$matches[] = $value;
				} else { //other 
					$matches[] = [
						'term' => [
							$field => $value,
						],
					];
				}
			}
		}
	}

	protected function performCount(Builder $builder, array $options = [])
	{
		$matches = $this->makeMatches($builder->query, $options['filters']);

		$query = [
			'index' =>  $this->index,
			'type'  =>  $builder->model->searchableAs(),
			'body' => [
				'query' => [
					'bool' => [
						'must' => $matches
					],
				],
			],
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
		
		$matches = $this->makeMatches($builder->query, $options['filters']);

		$query = [
			'index' =>  $this->index,
			'type'  =>  $builder->model->searchableAs(),
			'body' => [
				'_source' => $builder->_source,
				'query' => [
					'bool' => [
						'must' => $matches
					],
				],
			],
		];


		if (array_key_exists('size', $options)) {
			$query['size'] = $options['size'];
		}

		if (array_key_exists('from', $options)) {
			$query['from'] = $options['from'];
		}

		if ($builder->callback) {
			return call_user_func(
				$builder->callback,
				$this->elasticsearch,
				$query
			);
		}

		return $this->elasticsearch->search($query);
	}

	/**
     * Get the results of the query as a Collection of primary keys.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return \Illuminate\Support\Collection
     */
    public function keys(Builder $builder)
    {
    	$builder->_source = false; //elastic return no _source
        return $this->getIds($this->search($builder));
    }
}