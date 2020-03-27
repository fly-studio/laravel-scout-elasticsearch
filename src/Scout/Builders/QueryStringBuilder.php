<?php

namespace Addons\Elasticsearch\Scout\Builders;

use Addons\Elasticsearch\Scout\Builder;

class QueryStringBuilder extends Builder {

	/**
	 * A query that uses a query parser in order to parse its content.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html
	 *
	 */
	protected $query_string = null;

	public function __construct(Model $model, $stringOrRaw)
	{
		$this->setModel($model);
		$this->setQueryString(!is_array($stringOrRaw) ? ['query' => $stringOrRaw] : $stringOrRaw);
	}

	/**
	 * Add a constraint to the search query.
	 *
	 * @param  string  $field
	 * @param  mixed  $value
	 * @return $this
	 */
	public function where($field, $value)
	{
		throw new BadMethodCallException('Can not use "where" in "query_string" mode');
	}

	protected function prepareBody()
	{
		return [
			'query' => [
				'query_string' => $this->query_string,
			]
		];
	}

}
