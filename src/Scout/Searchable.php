<?php
namespace Addons\Elasticsearch\Scout;

use Laravel\Scout\Searchable as BaseSearchable;
use Addons\Elasticsearch\Scout\Builder;

trait Searchable {
	use BaseSearchable;

	/**
	 * Perform a search against the model's indexed data.
	 * @example 
	 * search()->where(...)->get()
	 * search(...)->keys()
	 * search(...)->where(...)->count()
	 * @example _all matach
	 * search('admin'); like where('_all', 'keywords');
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
	 * search($query); like where($query);
	 * @example associative array
	 * $query = [
	 *     'term' => [
	 *         'name' => 'admin'
	 *     ]
	 * ];
	 * search($query); like where($query);
	 * 
	 *
	 * @param  string|array  $_allOrCondition _all keywords or a array
	 * @param  string        $bool [must]|should|filter|must_not
	 * @param  Closure       $callback
	 * @return \Addons\Elasticsearch\Scout\Builder
	 */
	public static function search($_allOrCondition = null, $bool = 'must', $callback = null)
	{
		$builder = new Builder(new static, $bool, $callback);
		if (!is_null($_allOrCondition))
			!is_array($_allOrCondition) ? $builder->whereAll($_allOrCondition) : $builder->where($_allOrCondition);

		return $builder;
	}

}