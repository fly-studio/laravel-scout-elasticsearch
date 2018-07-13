<?php

namespace Addons\Elasticsearch\Scout;

use Laravel\Scout\ModelObserver;
use Laravel\Scout\SearchableScope;
use Addons\Elasticsearch\Scout\Builder;
use Laravel\Scout\Searchable as BaseSearchable;

trait Searchable {

	use BaseSearchable;
	use Indexable;

	/**
	 * Perform a search against the model's indexed data.
	 * @example
	 * search()->where(...)->get(['*'])
	 * search('shold')->where(...)->get(['*'])
	 * search()->where(...)->keys()
	 * search()->where(...)->count()
	 *
	 * @note
	 * querystring,bool,match_all is only one effective
	 *
	 *
	 * @param string         $boolOccur    [must]|should|filter|must_not https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
	 * @param Closure        $callback
	 * @return \Addons\Elasticsearch\Scout\Builder
	 */
	public static function search($boolOccur = 'must', $callback = null)
	{
		$builder = new Builder(new static, $callback);
		$builder->setBool($boolOccur);
		return $builder;
	}

	/**
	 * Boot the trait.
	 *
	 * @return void
	 */
	public static function bootSearchable()
	{
		static::addGlobalScope(new SearchableScope);

		if (!config('scout.disable_observer', false))
			static::observe(new ModelObserver);

		(new static)->registerSearchableMacros();
	}

	/**
	 * Make all instances of the model searchable.
	 *
	 * @return void
	 */
	public static function makeAllSearchable($min = 0, $max = 0)
	{
		$self = new static();

		$builder = $self->newQuery();
		if (!empty($min)) $builder->where($self->getKeyName(), '>=', $min);
		if (!empty($max) && $max >= $min) $builder->where($self->getKeyName(), '<=', $max);

		$builder->orderBy($self->getKeyName())
			->searchable();
	}

}
