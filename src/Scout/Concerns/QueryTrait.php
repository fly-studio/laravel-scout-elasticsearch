<?php

namespace Addons\Elasticsearch\Scout\Concerns;

use Illuminate\Pagination\Paginator;

trait QueryTrait {

	/**
	 * Allows to control how the _source field is returned with every hit.
	 * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-source-filtering.html
	 *
	 * @var boolean|array
	 */
	protected $_source = null;


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
	 * [get description]
	 * @param  array  $columns [description]
	 * @return [type]          [description]
	 */
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
	public function first(array $columns = ['*'])
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
		$this->set_source($columns);

		$page = $page ?: Paginator::resolveCurrentPage($pageName);

		$perPage = $perPage ?: $this->model->getPerPage();

		return $this->engine()->paginate($this, $perPage, $pageName, $page);
	}

}
