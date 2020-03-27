<?php

namespace Addons\Elasticsearch\Scout\Concerns;

trait OrderTrait {

	/**
	 * The "order" that should be applied to the search.
	 *
	 * @var array
	 */
	public $orders = [];

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
		{
			$this->orders[] = $column;
		}
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

}
