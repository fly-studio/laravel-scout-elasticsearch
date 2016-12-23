<?php
namespace Addons\Elasticsearch\Scout;

use Laravel\Scout\Builder as BaseBuilder;

class Builder extends BaseBuilder{

	public $_source = false;
	public $_count = false;

	public function get($columns = ['*'])
	{
		$this->_source = $columns;
		return parent::get();
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
    	return parent::paginate($perPage, $pageName, $page);
    }

}