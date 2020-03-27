<?php

namespace Addons\Elasticsearch\Scout\Builders;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Addons\Elasticsearch\Scout\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;

class BoolBuilder extends Builder {

	/**
	 * The "where" constraints added to the query.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
	 *
	 * @var array
	 */
	protected $bool = null;

	/**
	 * referencing like $this->bool['bool']['must']
	 */
	protected $bool_appended_pointer = null;

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

	public function __construct(Model $model, string $boolOccur = 'must', Collection $bool = null)
	{
		$this->setModel($model);

		$this->initBool($boolOccur, $bool);
	}

	protected function initBool(string $boolOccur = 'must', Collection $bool = null)
	{
		if (is_null($bool))
			$bool = new Collection();

		//create [bool][must]
		$bool['bool'] = new Collection([
			$boolOccur => new Collection(),
		]);

		$this->setBool($bool);
		$this->setBoolAppendedPointer($bool['bool'][$boolOccur]);
	}


	/**
	 * Add a constraint to the search query.
	 *
	 * @example where('_all', 'keywords') == whereAll('keywords');
	 * @example where('id', 1); == where('id', 'term', 1);
	 * @example where(['name', 'title'], 'admin'); == whereMultiMatch(['name', 'title'], 'admin');
	 * @example where('name', 'terms', ['admin', 'super']) == whereIn('name', ['admin', 'super']);
	 * @example where('name', 'match', 'ad');
	 * @example where('should', function($builder){ // sub where, like SQL: WHERE (`f1` = 1 OR `f2` = 2)
	 *     $builder->where(...)->where(...);
	 * });  == whereClosure('should', ...) == whereOr(...);
	 *
	 *
	 * @param  string|array  $column the field of elastic
	 * @param  string  $operator     |[term],=|terms,in|match|multi_match|prefix|common|like,wildcard|regexp|fuzzy|type|match_phrase|match_phrase_prefix|more_like_this|exists|>,gt|>=.gte|<,lt|<=,lte|range|
	 * @param  string|array          $value
	 * @param  array  $options      append to each where
	 * @return $this
	 */
	public function where($column, $operator = null, $value = null, ?array $options = [])
	{
		if ($column == '_all')
		{
			return $this->whereAll($column, $operator);

		} else if ($column instanceof Closure)
		{
			$boolOccur = $operator;
			$callable = $column;

			return $this->whereClosure($boolOccur, $callable);

		} else if(is_string($column) && $operator instanceof Closure) {
			$boolOccur = $column;
			$callable = $operator;

			return $this->whereClosure($boolOccur, $callable);

		} else
			$this->parseWhere($column, $operator, $value, $options);

		return $this;
	}

	/**
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
	 * User::search()->whereRaw($query)->get();
	 *
	 * @example associative array
	 * $query = [
	 *     'term' => [
	 *         'name' => 'admin'
	 *     ]
	 * ];
	 * User::search()->whereRaw($query)->get();
	 *
	 * @param  array  $esCondition
	 * @return $this
	 */
	public function whereRaw(array $esCondition)
	{
		$pointer = $this->bool_appended_pointer;

		if (!Arr::isAssoc($column))
			$pointer->merge($column);
		else
			$pointer[] = $column;

		return $this;
	}

	/**
	 * like above, but esCondition set in a "should"
	 * @param  array  $esCondition
	 * @return $this
	 */
	public function orWhereRaw(array $esCondition) {
		return $this->whereClosure('should', function($builder) use ($esCondition) {
			$builder->whereRaw($esCondition);
		});
	}

	/**
	 * User::search()->where('should', function($builder) {
	 * 	$builder->where(1)->where(2);
	 * });
	 *
	 * @param  string  $boolOccur must|must_not|should
	 * @param  Closure $callable
	 * @return $this
	 */
	public function whereClosure(?string $boolOccur, Closure $callable)
	{
		$this->bool_appended_pointer[] = new Collection();
		$bool = $this->bool_appended_pointer->last();

		$newBuilder = static::createFromBool($this->getModel(), $boolOccur, $bool);

		call_user_func_array($callable, [$newBuilder]);

		return $this;
	}

	/**
	 * User::search()->whereMultiMatch(['name', 'nickname'], 'super');
	 *
	 * @param  array  $fileds
	 * @param  mixed $value
	 * @return $this
	 */
	public function whereMultiMatch(array $fileds, $value)
	{
		return $this->where($fields, 'multi_match', $value);
	}

	/**
	 * like SQL's WHERE 1 or (`f` = 'v' and `f2` = 'v2')
	 *
	 * @example User::search()->whereOr('gender', 'famale')->get();
	 * @example User::search()->whereOr(function($builder) {
	 *          $builder->where(1)->where(2); // where not (1 and 2)
	 * })->get();
	 *
	 * @param  string|array $column   see where's column
	 * @param  string       $operator see where's operator
	 * @param  string|array $value    see where's value
	 * @param  array        $options  see where's options
	 * @return $this
	 */
	public function whereOr($column, $operator = null, $value = null, ?array $options = [])
	{
		return $this->whereClosure('should',
			$column instanceof Closure ?
				$column :
				function($builder) use ($column, $operator, $value, $options){
					$builder->where($column, $operator, $value, $options);
				}
		);
	}

	/**
	 * like SQL's WHERE `f` != 'v'
	 *
	 * @example User::search()->whereNot('gender', 'famale')->get();
	 * @example User::search()->whereNot(function($builder) {
	 *          $builder->where(1)->where(2); // where not (1 and 2)
	 * })->get();
	 *
	 * @param  string|array $column   see where's column
	 * @param  string       $operator see where's operator
	 * @param  string|array $value    see where's value
	 * @param  array        $options  see where's options
	 * @return $this
	 */
	public function whereNot($column, $operator = null, $value = null, ?array $options = [])
	{
		return $this->whereClosure('must_not',
			$column instanceof Closure ?
				$column :
				function($builder) use ($column, $operator, $value, $options){
					$builder->where($column, $operator, $value, $options);
				}
		);
	}

	/**
	 * like SQL: WHERE field is not null
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-query.html
	 *
	 * @example User::search('should')->whereExists('gender')->get(); = whereNotNull('gender')
	 * SELECT * FROM WHERE gender is not null
	 *
	 * @param  string $column
	 * @return $this
	 */
	public function whereExists(string $column)
	{
		return $this->where($column, 'exists', true);
	}

	/**
	 * Same as above
	 *
	 * @param  string $column
	 * @return $this
	 */
	public function whereNotNull(string $column) {
		return $this->whereExists($column);
	}

	/**
	 * like SQL: WHERE field is null
	 *
	 * @param  string $column
	 * @return $this
	 */
	public function whereNull(string $column) {
		return $this->whereNot(function($builder) use ($column) {
			$builder->whereExists($column);
		});
	}

	/**
	 * like SQL's where in
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
	 *
	 * @param  string           $column the field of elastic
	 * @param  array|Collection $values data
	 * @return $this
	 */
	public function whereIn($column, $values)
	{
		return $this->where($column, 'terms', (array)$values);
	}

	/**
	 * like SQL's WHERE `f` not in [$val]
	 *
	 * @param  string $column
	 * @param  array $values
	 * @return $this
	 */
	public function whereNotIn(string $column, $values)
	{
		return $this->whereNot($column, 'terms', (array)$values);
	}

	/**
	 * search all fields
	 *
	 * @param  string $value a string
	 * @return Builder       this
	 */
	public function whereAll($value)
	{
		$this->bool_appended_pointer[] = [
			'match' => [
				'_all' => [
					'query' => $value,
					'fuzziness' => 1,
				],
			],
		];
		return $this;
	}

	protected function parseWhere($column, $operator, $value, ?array $options = [])
	{
		// where('name', 'foo')
		// where(['name', 'nickname'], 'foo')
		if (is_null($value) && !is_null($operator)) //set default operator
		{
			$value = $operator;
			$operator = is_array($column) ? 'multi_match' : 'term';
		}

		$pointer = $this->bool_appended_pointer;

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

	protected function prepareBody()
	{
		// table contain deleted_at
		if (isset($this->wheres['__soft_deleted']))
		{
			//only trashed: deleted_at is not null
			if ($this->wheres['__soft_deleted'] === 1)
				$this->whereNotNull($this->getModel()->getDeletedAtColumn());
			else  //All without trashed: deleted_at is null
				$this->whereNull($this->getModel()->getDeletedAtColumn());
		} // all with trashed

		return [
			'query' => $this->bool->toArray(),
		];
	}
}
