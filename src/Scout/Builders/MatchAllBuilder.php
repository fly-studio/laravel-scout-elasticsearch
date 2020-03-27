<?php

namespace Addons\Elasticsearch\Scout\Builder;

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

	protected function prepareBody()
	{
		return [
			'query' => [
				'match_all' => $this->match_all,
			]
		];
	}

}
