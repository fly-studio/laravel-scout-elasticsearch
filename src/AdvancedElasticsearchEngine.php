<?php
namespace Addons\Elasticsearch;

use Laravel\Scout\Engines\ElasticsearchEngine;
use Laravel\Scout\Builder;
class AdvancedElasticsearchEngine extends ElasticsearchEngine {


	

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
		$filters = $matches = [];
		if (!empty($builder->query))
			$matches[] = [
				'match' => [
					'_all' => [
						'query' => $builder->query,
						'fuzziness' => 1
					]
				]
			];

		if (array_key_exists('filters', $options) && $options['filters']) {
			foreach ($options['filters'] as $field => $value) {

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
}