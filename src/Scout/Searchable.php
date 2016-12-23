<?php
namespace Addons\Elasticsearch\Scout;

use Laravel\Scout\Searchable as BaseSearchable;
use Addons\Elasticsearch\Scout\Builder;

trait Searchable {
	use BaseSearchable;

	/**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  Closure  $callback
     * @return \Addons\Elasticsearch\Scout\Builder
     */
    public static function search($query, $callback = null)
    {
        return new Builder(new static, $query, $callback);
    }

}