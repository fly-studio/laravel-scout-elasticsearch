<?php

namespace Addons\Elasticsearch\Scout\Concerns;

trait AggregateTrait {

	/**
	 * The "aggs" constraints added to the body.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations.html
	 *
	 * @var array
	 */
	public $aggs = null;

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
	 * Get the aggregations from the search
	 * [warning] one search with one 'aggregations' at builder's end
	 *
	 * @return mixed
	 */
	public function aggregations($projectionKey = null, bool $noSource = true)
	{
		if ($noSource) $this->take(0)->set_source(false);

		return $this->engine()->aggregations($this, $projectionKey);
	}

}
