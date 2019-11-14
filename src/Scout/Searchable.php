<?php

namespace Addons\Elasticsearch\Scout;

use Carbon\Carbon;
use Laravel\Scout\ModelObserver;
use Addons\Elasticsearch\Scout\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Addons\Elasticsearch\Scout\MakeSearchable;
use Addons\Elasticsearch\Scout\SearchableScope;
use Laravel\Scout\Searchable as BaseSearchable;
use Illuminate\Support\Collection as BaseCollection;

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
	public static function search(string $boolOccur = 'must', callable $callback = null)
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

		if (!empty($observer = config('scout.observer', false)))
			static::observe(new $observer);

		(new static)->registerSearchableMacros();
	}

	/**
	 * Make all instances of the model searchable.
	 *
	 * @return void
	 */
	public static function makeAllSearchable($min = 0, $max = 0, bool $refresh = true)
	{
		$self = new static();

		$softDelete = static::usesSoftDelete() && config('scout.soft_delete', false);

		$builder = $self->newQuery();

		if (!empty($min)) $builder->where($self->getKeyName(), '>=', $min);
		if (!empty($max) && $max >= $min) $builder->where($self->getKeyName(), '<=', $max);

		$builder
			->when($softDelete, function ($query) {
				$query->withTrashed();
			})
			->orderBy($self->getKeyName())
			->searchable(null, $refresh);
	}

	/**
	 * Register the searchable macros.
	 *
	 * @return void
	 */
	public function registerSearchableMacros()
	{
		$self = $this;

		BaseCollection::macro('searchable', function (bool $refresh = true) use ($self) {
			$self->queueMakeSearchable($this, $refresh);
		});

		BaseCollection::macro('unsearchable', function (bool $refresh = true) use ($self) {
			$self->queueRemoveFromSearch($this, $refresh);
		});
	}

	/**
	 * Dispatch the job to make the given models searchable.
	 *
	 * @param  \Illuminate\Database\Eloquent\Collection  $models
	 * @return void
	 */
	public function queueMakeSearchable($models, bool $refresh = true)
	{
		if ($models->isEmpty()) {
			return;
		}

		if (! config('scout.queue'))
		{
			$models->loadMissing($models->first()->searchableWith());

			return $models->first()->searchableUsing()->update($models, $refresh);
		}

		dispatch((new MakeSearchable($models, $refresh))
				->onQueue($models->first()->syncWithSearchUsingQueue())
				->delay($models->first()->syncWithSearchableDelay())
				->onConnection($models->first()->syncWithSearchUsing()));
	}

	/**
	 * Dispatch the job to make the given models unsearchable.
	 *
	 * @param  \Illuminate\Database\Eloquent\Collection  $models
	 * @return void
	 */
	public function queueRemoveFromSearch($models, bool $refresh = true)
	{
		if ($models->isEmpty()) {
			return;
		}

		return $models->first()->searchableUsing()->delete($models, $refresh);
	}

	public function searchableWith()
	{
		return [];
	}

	public function syncWithSearchableDelay()
	{
		return config('scout.queue.delay', Carbon::now());
	}

}
