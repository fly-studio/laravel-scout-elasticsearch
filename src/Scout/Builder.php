<?php

namespace Addons\Elasticsearch\Scout;

use Closure;
use BadMethodCallException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;

// see Laravel\Scout\Builder
class Builder extends \Laravel\Scout\Builder {

	/**
	 * The model instance.
	 *
	 * @var \Illuminate\Database\Eloquent\Model
	 */
	public $model;

	/**
	 * The _all's keywords.
	 *
	 * @var string
	 */
	private $_all;

	/**
	 * Optional callback before search execution.
	 *
	 * @var string
	 */
	public $callback;

	/**
	 * The custom index specified for the search.
	 *
	 * @var string
	 */
	public $index = null;

	/**
	 * A query that uses a query parser in order to parse its content.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html
	 *
	 */
	public $query_string = null;
	/**
	 * Allows to control how the _source field is returned with every hit.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-source-filtering.html
	 *
	 * @var boolean|array
	 */
	public $_source = null;

	/**
	 * The most simple query, which matches all documents
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-all-query.html
	 *
	 * query_string match_all
	 *
	 * @var  array
	 */
	public $match_all = null;

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
	 * referencing like $this->bool['bool']['must']
	 */
	private $boolAppendedPointer = null;

	/**
	 * The "where" constraints added to the query.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
	 *
	 * @var array
	 */
	public $bool = null;

	/**
	 * The "aggs" constraints added to the body.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations.html
	 *
	 * @var array
	 */
	public $aggs = null;

	/**
	 * The "limit" that should be applied to the search.
	 *
	 * @var int
	 */
	public $limit = null;

	/**
	 * The "offset" that should be applied to the search.
	 *
	 * @var int
	 */
	public $offset = 0;

	/**
	 * The "order" that should be applied to the search.
	 *
	 * @var array
	 */
	public $orders = [];

	/**
	 * the operator's alias
	 *
	 * @var array
	 */
	protected $aliasOperators = [
		'=' => 'term',
		'in' => 'terms',
		'>' => 'gt',
		'>=' => 'gte',
		'<' => 'lt',
		'<=' => 'lte',
		'like' => 'wildcard',
		'mlt' => 'more_like_this',
	];

	/**
	 * Create a new search builder instance.
	 *
	 * @param  \Illuminate\Database\Eloquent\Model  $model
	 * @param  Closure  $callback
	 * @return void
	 */
	public function __construct($model, $callback = null)
	{
		$this->model = $model;
		$this->callback = $callback;
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
	 * Set the "limit" for the search query.
	 *
	 * @param  int  $limit
	 * @return $this
	 */
	public function take($limit)
	{
		$this->limit = $limit;

		return $this;
	}

	/**
	 * Set the "offset" for the search query.
	 *
	 * @param  int  $offset
	 * @return $this
	 */
	public function offset($offset)
	{
		$this->offset = $offset;

		return $this;
	}

	/**
	 * Get the keys of search results.
	 *
	 * @return \Illuminate\Support\Collection
	 */
	public function keys()
	{
		$this->set_source(false); //elastic return no _source
		return $this->engine()->keys($this);
	}

	/**
	 * Add an "order" for the search query.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-sort.html
	 *
	 * @param  string  $column
	 * @param  string  $direction [asc]|desc
	 * @param  string  $mode null|min|max|sum|avg|median
	 * @param  string  $options nested_path,nested_filter,missing,unmapped_type,_geo_distance
	 * @return $this
	 */
	public function orderBy($column, $direction = 'asc', $mode = null, $options = [])
	{
		if (is_null($direction))
			$this->orders[] = $column;
		else
		{
			$this->orders[$column] = [
				'order' => strtolower($direction) == 'asc' ? 'asc' : 'desc',
			] + $options;

			if (!is_null($mode)) $this->orders[$column] += [
				'mode' => $mode,
			];
		}


		return $this;
	}

	public function get(array $columns = ['*'])
	{
		$this->set_source($columns);
		return $this->engine()->get($this);
	}

	/**
	 * Get the first result from the search.
	 *
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	public function first($columns = ['*'])
	{
		$this->set_source($columns)->take(1);
		return $this->get()->first();
	}

	/**
	 * Get the count from the search.
	 * it's easy way, with _count API of elastic
	 *
	 * @return int
	 */
	public function count()
	{
		return $this->engine()->count($this);
	}

	/**
	 * Get the RAW from the search
	 *
	 * @return array
	 */
	public function execute()
	{
		return $this->engine()->execute($this);
	}

	/**
	 * Get the aggregations from the search
	 * [warning] one search with one 'aggregations' at builder's end
	 *
	 * @return mixed
	 */
	public function aggregations($projectionKey = null, $noSource = true)
	{
		if ($noSource) $this->take(0)->set_source(false);
		return $this->engine()->aggregations($this, $projectionKey);
	}

	/**
	 * Paginate the given query into a simple paginator.
	 *
	 * @param  int  $perPage
	 * @param  boolean|array filter columns form _source
	 * @param  string  $pageName
	 * @param  int|null  $page
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
	 */
	public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
	{
		$this->_source = $columns;

		$page = $page ?: Paginator::resolveCurrentPage($pageName);

		$perPage = $perPage ?: $this->model->getPerPage();

		return $this->engine()->paginate($this, $perPage, $pageName, $page);
	}

	/**
	 * set wheres
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
	 *
	 * setMatchAll,setQueryString,setBool only 1 run
	 *
	 * @param string       $boolOccur    [must]|should|filter|must_not
	 * @param Collection   $wheres       the where's array
	 */
	public function setBool(string $boolOccur = 'must', Collection $bool = null)
	{
		is_null($bool) && $bool = new Collection();
		//create [bool][must]
		$bool['bool'] = new Collection([
			$boolOccur => new Collection(),
		]);
		$this->bool = $bool;
		$this->boolAppendedPointer = $bool['bool'][$boolOccur];
		return $this;
	}

	/**
	 * A query that uses a query parser in order to parse its content.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html
	 *
	 * setMatchAll,setQueryString,setBool only 1 run
	 *
	 * @param mixed $stringOrArray string|array
	 */
	public function setQueryString($stringOrArray)
	{
		$this->query_string = !is_array($stringOrArray) ? ['query' => $stringOrArray] : $stringOrArray;
		return $this;
	}

	/**
	 * The most simple query, which matches all documents
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-all-query.html
	 *
	 * setMatchAll,setQueryString,setBool only 1 run
	 *
	 * @param mixed $stringOrArray string|array
	 */
	public function setMatchAll($match_all)
	{
		$this->match_all = $match_all;
		return $this;
	}


	/**
	 * The "aggs" constraints added to the body.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations.html
	 *
	 * @param array
	 */
	public function setAggs($aggs)
	{
		$this->aggs = $aggs;
		return $this;
	}

	/**
	 * Add a constraint to the search query.
	 *
	 * @example where('_all', 'keywords');
	 * @example where('id', 1); == where('id', 'term', 1);
	 * @example where(['name', 'title'], 'admin'); == where(['name', 'title'], 'multi_match', 'admin');
	 * @example where('name', 'terms', ['admin', 'super']);
	 * @example where('name', 'match', 'ad');
	 * @example sequential numerical array list
	 * $query = [
	 *     [
	 *         'term' => [
	 *             'name' => 'admin'
	 *         ]
	 *     ],
	 *     [
	 *         'multi_match' => [
	 *             'fields' => ['name', 'title'],
	 *             'query' => 'admin'
	 *         ]
	 *     ]
	 * ];
	 * where($query);
	 * @example associative array
	 * $query = [
	 *     'term' => [
	 *         'name' => 'admin'
	 *     ]
	 * ];
	 * where($query);
	 * @example sub where, like SQL: WHERE (`f1` = 1 OR `f2` = 2)
	 * where(function($builder, 'should'){
	 *     $builder->where(...)->where(...);
	 * });
	 *
	 * @param  string|array  $column the field of elastic
	 * @param  string  $operator     |[term],=|terms,in|match|multi_match|prefix|common|like,wildcard|regexp|fuzzy|type|match_phrase|match_phrase_prefix|more_like_this|exists|>,gt|>=.gte|<,lt|<=,lte|range|
	 * @param  string|array          $value
	 * @param  array  $options      append to each where
	 * @return $this
	 */
	public function where($column, $operator = null, $value = null, $options = [])
	{
		if ($column instanceof Closure)
		{
			$boolOccur = $operator;
			$this->boolAppendedPointer[] = new Collection();
			$bool = $this->boolAppendedPointer->last();
			$new = new static($this->model, null);
			$new->setBool($boolOccur, $bool);
			call_user_func_array($column, [$new]);
		} else
			$this->parseWhere($column, $operator, $value, $options);

		return $this;
	}

	/**
	 * like SQL's where in
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
	 *
	 * @param  string           $column the field of elastic
	 * @param  array|Collection $values data
	 * @return Builder                  this
	 */
	public function whereIn($column, $values)
	{
		return $this->where($column, 'terms', (array)$values);
	}

	/**
	 * search all fields
	 *
	 * @param  string $value a string
	 * @return Builder       this
	 */
	public function whereAll($value)
	{
		$this->_all = $value;
		return $this->where('_all', $value);
	}

	public function whereOr($column, $operator = null, $value = null, $options = [])
	{
		return $this->where(function($builder) use ($column, $operator, $value, $options){
			$builder->where($column, $operator, $value, $options);
		}, 'should');
	}


	/**
	 * like SQL: is null
	 * @example User::search('should')->whereExists('gender')->where('gender', 'male')->get(); SELECT * FROM (gender is null OR gender = 'male')
	 *
	 * @param  [type] $column [description]
	 * @return [type]         [description]
	 */
	public function whereExists($column)
	{
		return $this->where($column, 'exists', '');
	}

	/**
	 * like SQL's WHERE `f` != 'v'
	 *
	 * @example User::search()->whereNot('gender', 'famale')->get();
	 * @example User::search()->whereNot(function($query) {
	 *          $query->where(1)->where(2); // where not (1 and 2)
	 * })->get();
	 *
	 * @param  string|array $column   see where's column
	 * @param  string       $operator see where's operator
	 * @param  string|array $value    see where's value
	 * @param  array        $options  see where's options
	 * @return $this
	 */
	public function whereNot($column, $operator = null, $value = null, $options = [])
	{
		return $this->where(function($builder) use ($column, $operator, $value, $options){
			$builder->where($column, $operator, $value, $options);
		}, 'must_not');
	}

	/**
	 * like SQL's WHERE `f` not in [$val]
	 *
	 * @param  [type] $column [description]
	 * @param  [type] $values [description]
	 * @return [type]         [description]
	 */
	public function whereNotIn($column, $values)
	{
		return $this->whereNot($column, 'terms', $values);
	}

	protected function parseWhere($column, $operator, $value, $options = [])
	{
		if (is_null($value) && !is_null($operator)) //set default operator
		{
			$value = $operator;
			$operator = is_array($column) ? 'multi_match' : 'term';
		}
		$pointer = $this->boolAppendedPointer;

		if ($column == '_all') // _all
		{
			$pointer[] = [
				'match' => [
					'_all' => [
						'query' => $value,
						'fuzziness' => 1,
					],
				],
			];
		}
		else if (is_array($column) && is_null($value) && is_null($operator)) //array, append data
		{
			if (!Arr::isAssoc($column))
				$pointer->merge($column);
			else
				$pointer[] = $column;
		}
		else
		{
			$operator = strtolower($operator);
			isset($this->aliasOperators[$operator]) && $operator = $this->aliasOperators[$operator];

			$value instanceof Arrayable && $value = $value->toArray();

			switch ($operator) {
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-term-query.html
				case 'term':
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-prefix-query.html
				case 'prefix':
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-wildcard-query.html
				case 'wildcard':
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-regexp-query.html
				case 'regexp':
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-fuzzy-query.html
				case 'fuzzy':
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-type-query.html
				case 'type':
					$pointer[] = [
						$operator => [
							$column => [
							   'value' => $value,
							] + $options,
						],
					];
					break;
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query.html
				case 'match':
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query-phrase.html
				case 'match_phrase':
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query-phrase-prefix.html
				case 'match_phrase_prefix':
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-common-terms-query.html
				case 'common':
					$pointer[] = [
						$operator => [
							$column => [
							   'query' => $value,
							] + $options,
						],
					];
					break;
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
				case 'terms':
					$pointer[] = [
						$operator => [
							$column =>  $value,
						],
					];
					break;
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
				case 'gt':
				case 'gte':
				case 'lt':
				case 'lte':
					$pointer[] = [
						'range' => [
							$column => [
								$operator => $value,
							] + $options
						],
					];
					break;
				case 'range':
					$pointer[] = [
						'range' => [
							$column => $value + $options,
						],
					];
					break;
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html
				case 'multi_match':
					$pointer[] = [
						'multi_match' => [
							'fields' => $column,
							'query' => $value,
						] + $options,
					];
					break;
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-mlt-query.html
				case 'more_like_this';
					$pointer[] = [
						'more_like_this' => [
							'fields' => $column,
							'like' => $value,
						] + $options,
					];
					break;
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-query.html
				case 'exists':
					$pointer[] = [
						'exists' => [
							'field' => $column,
						],
					];
					break;
				default:

					break;
			}
		}
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

}
