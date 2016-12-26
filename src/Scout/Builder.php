<?php
namespace Addons\Elasticsearch\Scout;

use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Closure;

//see Laravel\Scout\Builder
class Builder {

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
	public $index;

	/**
	 * Allows to control how the _source field is returned with every hit.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-source-filtering.html
	 * @var boolean|array
	 */
	public $_source = false;

	/**
	 * The most simple query, which matches all documents
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-all-query.html
	 * @var  array
	 */
	public $match_all = [];

	/**
	 * $this->wheres['bool']['must']
	 */
	private $whereAppendedPointer;

	/**
	 * The "where" constraints added to the query.
	 *
	 * @var array
	 */
	public $wheres = [];

	/**
	 * The "limit" that should be applied to the search.
	 *
	 * @var int
	 */
	public $limit;

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
		'like' => 'match',
		'mlt' => 'more_like_this',
	];

	/**
	 * Create a new search builder instance.
	 *
	 * @param  \Illuminate\Database\Eloquent\Model  $model
	 * @param  bool  [must]|should|filter|must_not https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
	 * @param  Closure  $callback
	 * @param  Collection $parentWheres the parent wheres, for nested where
	 * @return void
	 */
	public function __construct($model, $bool = 'must', $callback = null, Collection $parentWheres = null)
	{
		$this->model = $model;
		is_null($parentWheres) && $parentWheres = new Collection();
		//create [bool][must]
		$parentWheres['bool'] = new Collection([
			$bool => new Collection(),
		]);
		$this->setWheres($parentWheres, $parentWheres['bool'][$bool]); //Object is referenced
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
	 * Get the keys of search results.
	 *
	 * @return \Illuminate\Support\Collection
	 */
	public function keys()
	{
		return $this->engine()->keys($this);
	}

	/**
	 * Add an "order" for the search query.
	 *
	 * @param  string  $column
	 * @param  string  $direction
	 * @return $this
	 */
	public function orderBy($column, $direction = 'asc')
	{
		$this->orders[] = [
			'column' => $column,
			'direction' => strtolower($direction) == 'asc' ? 'asc' : 'desc',
		];

		return $this;
	}

	public function get($columns = ['*'])
	{
		$this->_source = $columns;
		return $this->engine()->get($this);
	}

	/**
	 * Get the first result from the search.
	 *
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	public function first($columns = ['*'])
	{
		$this->_source = $columns;
		return $this->get()->first();
	}

	/**
	 * Get the count from the search.
	 * it's easy way, with _count API of elastic 
	 *
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	public function count()
	{
		return $this->engine()->count($this);
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

		$engine = $this->engine();

		$page = $page ?: Paginator::resolveCurrentPage($pageName);

		$perPage = $perPage ?: $this->model->getPerPage();

		$results = Collection::make($engine->map(
			$rawResults = $engine->paginate($this, $perPage, $page), $this->model
		));

		$paginator = (new LengthAwarePaginator($results, $engine->getTotalCount($rawResults), $perPage, $page, [
			'path' => Paginator::resolveCurrentPath(),
			'pageName' => $pageName,
		]));

		return $paginator->append(['_all' => $this->_all]);
	}

	/**
	 * set wheres
	 * @param Collection      $wheres          the where's array
	 * @param Collection|null $whereAppendedPointer the pointer that appending data
	 */
	public function setWheres(Collection $wheres, Collection $whereAppendedPointer = null)
	{
		$this->wheres = $wheres;
		$this->whereAppendedPointer = is_null($whereAppendedPointer) ? $wheres : $whereAppendedPointer;
	}

	/**
	 * set match_all
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-all-query.html
	 * 
	 * @param array $match_all [description]
	 */
	public function setMatchAll(array $match_all)
	{
		$this->match_all = $match_all;
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
	 * @param  string  $operator     [term],=|terms,in|match,like|multi_match|range|prefix|common|wildcard|regexp|fuzzy|type|match_phrase|match_phrase_prefix|more_like_this|exists
	 * @param  string|array          $value
	 * @param  array  $options      append to each where
	 * @return $this
	 */
	public function where($column, $operator = null, $value = null, $options = [])
	{
		if ($column instanceof Closure)
		{
			$bool = $operator;
			$this->whereAppendedPointer[] = new Collection();
			$wheres = $this->whereAppendedPointer->last();
			$new = new static($this->model, $bool, null, $wheres);
			call_user_func_array($column, [$new]);
		} else
			$this->parseWhere($column, $operator, $value);

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
		return $this->where($column, 'terms', $values);
	}

	public function whereAll($value)
	{
		$this->_all = $value;
		return $this->where('_all', $value);
	}

	/**
	 * like SQL's where `f` != 'v'
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
	 * like SQL's where not in
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
		if (is_null($value) && !is_null($operator)) { //set default operator
			$value = $operator;
			$operator = is_array($column) ? 'multi_match' : 'term';
		}
		$pointer = $this->whereAppendedPointer;

		if ($column == '_all') { // _all
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
				$pointer->merge($columns);
			else
				$pointer[] = $columns;
		}
		else
		{
			$operator = strtolower($operator);
			isset($this->aliasOperators[$operator]) && $operator = $this->aliasOperators[$operator];
			$value instanceof Collection && $value = $value->toArray();

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
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
				case 'terms':
					$pointer[] = [
						$operator => [
							$column =>  $value,
						],
					];
					break;
				//https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
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

}