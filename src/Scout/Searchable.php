<?php
namespace Addons\Elasticsearch\Scout;

use Laravel\Scout\Searchable as BaseSearchable;
use Addons\Elasticsearch\Scout\Builder;

trait Searchable {
	use BaseSearchable;

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

}