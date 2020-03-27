<?php

namespace Addons\Elasticsearch\Scout\Builders;

use Addons\Elasticsearch\Scout\Builder;
use Elasticsearch\Common\Exceptions\BadMethodCallException;

class MatchAllBuilder extends Builder {

	/**
	 * The most simple query, which matches all documents
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-all-query.html
	 *
	 * query_string match_all
	 *
	 * @var  array
	 */
	protected $match_all = null;

	public function __construct(Model $model, $stringOrArray)
	{
		$this->setModel($model);
		$this->setMatchAll($stringOrArray);
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
		throw new BadMethodCallException('Can not use "where" in "match_all" mode');
	}

	protected function prepareBody()
	{
		return [
			'query' => [
				'match_all' => $this->match_all,
			]
		];
	}

}
