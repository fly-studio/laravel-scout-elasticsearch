<?php

namespace Addons\Elasticsearch\Scout;

use Closure;
use BadMethodCallException;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

// see Laravel\Scout\Builder
abstract class Builder extends \Laravel\Scout\Builder {

	use Concerns\OrderTrait;
	use Concerns\QueryTrait;
	use Concerns\AggregateTrait;

	/**
	 * The model instance.
	 *
	 * @var \Illuminate\Database\Eloquent\Model
	 */
	protected $model;

	/**
	 * Optional callback before search execution.
	 *
	 * @var string
	 */
	protected $callback;

	/**
	 * The custom index specified for the search.
	 *
	 * @var string
	 */
	protected $index = null;

	/**
	 * When sorting on a field, scores are not computed. By setting track_scores to true, scores will still be computed and tracked.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-sort.html#_track_scores
	 *
	 * @var boolean
	 */
	public $track_scores = null;

	/**
	 * The stored_fields parameter is about fields that are explicitly marked as stored in the mapping, which is off by default and generally not recommended
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-stored-fields.html
	 *
	 * @var array
	 */
	public $stored_fields = null;

	/**
	 * Allows to return the doc value representation of a field for each hit
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-docvalue-fields.html
	 *
	 * @var array
	 */
	public $docvalue_fields = null;

	/**
	 * Allows to highlight search results on one or more fields.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-highlighting.html
	 *
	 * @var array
	 */
	public $highlight = null;

	/**
	 * Rescoring can help to improve precision by reordering just the top (eg 100 - 500) documents returned by the query and post_filter phases, using a secondary (usually more costly) algorithm, instead of applying the costly algorithm to all documents in the index.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-rescore.html
	 *
	 * @var array
	 */
	public $rescore = null;

	/**
	 * Enables explanation for each hit on how its score was computed.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-explain.html
	 *
	 * @var boolean
	 */
	public $explain = null;

	/**
	 * Returns a version for each search hit.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-version.html
	 *
	 * @var boolean
	 */
	public $version = null;

	/**
	 * Allows to configure different boost level per index when searching across more than one indices.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-index-boost.html
	 *
	 * @var array
	 */
	public $indices_boost = null;

	/**
	 * Pagination of results can be done by using the from and size but the cost becomes prohibitive when the deep pagination is reached
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-search-after.html
	 *
	 * @var float
	 */
	public $min_score = null;

	/**
	 * Exclude documents which have a _score less than the minimum specified in min_score
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-min-score.html
	 *
	 * @var float
	 */
	public $search_after = null;

	/**
	 * set wheres
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
	 *
	 * @param string       $boolOccur    [must]|should|filter|must_not
	 * @param Collection   $wheres       the where's array
	 */
	public static function createFromBool(Model $model, string $boolOccur = 'must', Collection $bool = null)
	{
		return new Builders\BoolBuilder($model, $boolOccur, $bool);
	}

	/**
	 * The most simple query, which matches all documents
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-all-query.html
	 *
	 * @param mixed $stringOrRaw string|array
	 */
	public static function createFromMatchAll(Model $model, $stringOrRaw)
	{
		return new Builders\MatchAllBuilder($model, $stringOrRaw);
	}

	/**
	 * A query that uses a query parser in order to parse its content.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html
	 *
	 * @param mixed $stringOrRaw string|array
	 */
	public static function createFromQueryString(Model $model, $stringOrRaw)
	{
		return new Builders\QueryStringBuilder($model, $stringOrRaw);
	}

	/**
	 * Specify a custom index to perform this search on.
	 *
	 * @param  string  $index
	 * @return $this
	 */
	public function within($index)
	{
		$this->index = $index;

		return $this;
	}


	/**
	 * Get the engine that should handle the query.
	 *
	 * @return mixed
	 */
	protected function engine()
	{
		return $this->model->searchableUsing();
	}

	public function __call($method, $parameters)
	{
		if (isset($parameters[0]) && Str::startsWith($method, 'set'))
		{
			$var = Str::snake(Str::substr($method, 3));

			if (property_exists($this, $var)) {
				$this->$var = $parameters[0];
				return $this;
			}

		} elseif (empty($parameters[0]) && Str::startsWith($method, 'get')) {
			$var = Str::snake(Str::substr($method, 3));

			if (property_exists($this, $var))
				return $this->$var;
		}

		throw new BadMethodCallException("Method [{$method}] does not exist.");

	}

	public function getBody()
	{
		$body = $builder->prepareBody();

		foreach([
				'_source',
				'aggs',
				'track_scores',
				'stored_fields',
				'docvalue_fields',
				'highlight',
				'rescore',
				'explain',
				'version',
				'indices_boost',
				'min_score',
				'search_after'
			] as $var)
		{
			$value = $builder->$var;

			if (!is_null($value))
				$body[$var] = $value;
		}

		return $body;
	}

	public function getBodyWithOrders()
	{
		return $this->getBody() + [
			'sort' => $this->getOrders(),
		];
	}

	abstract protected function prepareBody();

}
