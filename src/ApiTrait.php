<?php

namespace Addons\Elasticsearch;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Addons\Elasticsearch\Scout\Builder;
use Addons\Core\Exceptions\OutputResponseException;
use Elasticsearch\Common\Exceptions\ServerErrorResponseException;

trait ApiTrait {

	protected $apiOperators = [
		'in' => 'terms', 'nin' => 'not terms',
		'min' => 'gte', 'max' => 'lte',
		'neq' => 'not =', 'ne' => 'not =', 'eq' => '=', 'equal' => '=',
		'lk' => 'wildcard', 'like' => 'wildcard',
		'nlk' => 'not wildcard',
		'match' => 'match', 'multi_match' => 'multi_match', 'range' => 'range', 'prefix' => 'prefix', 'common' => 'common', 'wildcard' => 'wildcard', 'regexp' => 'regexp', 'fuzzy' => 'fuzzy', 'type' => 'type', 'match_phrase' => 'match_phrase', 'match_phrase_prefix' => 'match_phrase_prefix', 'more_like_this' => 'more_like_this', 'exists' => 'exists',
	];

	/**
	 * 给Builder绑定where条件
	 * 注意：参数的值为空字符串，则会忽略该条件
	 *
	 * @param  Request $request
	 * @param  Builder $builder
	 * @return array           返回筛选(搜索)的参数
	 */
	private function _doFilters(Request $request, Builder $builder)
	{
		$filters = $this->_getFilters($request);

		foreach ($filters as $key => $filter)
		{
			foreach ($filter as $method => $value)
			{
				if (empty($value) && !is_numeric($value)) continue; //''不做匹配

				$operator = $this->apiOperators[$method] ?: $method;
				$condition = 'where';

				if (strpos($operator, 'not') === 0){
					$condition = 'whereNot';
					$operator = trim(substr($operator, 3));
				}

				if(in_array($operator, ['gt', 'gte', 'lt', 'lte']))
				{
					$value = [$operator => $value];
					$operator = 'range';
				}

				if ($operator == 'wildcard')
					$value = '*'.trim($value, '*').'*'; //添加开头结尾的*

				$builder->$condition($key, $operator ?: '=' , $value);
			}
		}
		return $filters;
	}

	private function _doOrders(Request $request, Builder $builder)
	{
		$orders = $this->_getOrders($request, $builder);
		foreach ($orders as $k => $v)
			$builder->orderBy($k, $v);
		return $orders;
	}
	/**
	 * 获取筛选(搜索)的参数
	 * &f[username][lk]=abc&f[gender][eq]=1
	 *
	 * @param  Request $request
	 * @param  Builder $builder
	 * @return array           返回参数列表
	 */
	public function _getFilters(Request $request)
	{
		$filters = [];
		$inputs = $request->input('f', []);
		if (!empty($inputs))
			foreach ($inputs as $k => $v)
				$filters[$k] = is_array($v) ? array_change_key_case($v) : ['eq' => $v];

		return $filters;
	}

	/**
	 * 获取排序的参数
	 * 1. datatable 的方式
	 * 2. order[id]=desc&order[created_at]=asc 类似这种方式
	 * 默认是按主键倒序
	 *
	 * @param  Request $request
	 * @param  Builder $builder
	 * @return array           返回参数列表
	 */
	public function _getOrders(Request $request, Builder $builder)
	{
		$orders = $request->input('o', []);
		//默认按照主键的倒序
		return empty($orders) ? [$builder->getModel()->getKeyName() => 'desc'] : $orders;
	}

	public function _getPaginate(Request $request, Builder $builder, array $columns = ['*'], array $extra_query = [])
	{
		$size = $request->input('size') ?: config('size.models.'.$builder->getModel()->getTable(), config('size.common'));
		$page = $request->input('page', 1);
		if ($request->input('all') == 'true') $size = 10000;//$builder->count(); //为统一使用paginate输出数据格式,这里需要将size设置为整表数量

		$filters = $this->_doFilters($request, $builder);
		$orders = $this->_doOrders($request, $builder);

		try {
			$paginate = $builder->paginate($size, $columns, 'page', $page);
		} catch (ServerErrorResponseException $e) {

			if (stripos($e->getMessage(), 'Result window is too large') !== false)
				throw new OutputResponseException('es::exceptions.out_of_page');
			else
				throw new OutputResponseException('es::exceptions.ServerErrorResponseException');
		}

		$query_strings = array_merge_recursive(['f' => $filters], $extra_query);
		$paginate->appends($query_strings);

		$paginate->filters = $filters;
		$paginate->orders = $orders;
		return $paginate;
	}

	public function _getData(Request $request, Builder $builder, callable $callback = null, array $columns = ['*'])
	{
		$paginate = $this->_getPaginate($request, $builder, $columns);

		if (is_callable($callback))
			call_user_func_array($callback, [$paginate]); //reference Objecy

		return $paginate->toArray() + ['filters' => $paginate->filters, 'orders' => $paginate->orders];
	}

	public function _getCount(Request $request, Builder $builder, $enable_filters = true)
	{
		$_b = clone $builder;
		if ($enable_filters)
			$this->_doFilters($request, $_b);

		return $_b->count();
	}

	public function _getExport(Request $request, Builder $builder, callable $callback = null, array $columns = ['*'])
	{
		set_time_limit(600); //10min

		$size = $request->input('size') ?: config('size.export', 1000);

		$this->_doFilters($request, $builder);

		try {

			$paginate = $builder->orderBy($builder->getModel()->getKeyName(), 'DESC')->paginate($size, $columns);

		} catch (ServerErrorResponseException $e) {

			if (stripos($e->getMessage(), 'Result window is too large') !== false)
				throw new OutputResponseException('es::exceptions.out_of_page');
			else
				throw new OutputResponseException('es::exceptions.ServerErrorResponseException');
		}

		if (is_callable($callback))
			call_user_func_array($callback, [$paginate]);

		$data = $paginate->toArray();

		!empty($data['data']) && Arr::isAssoc($data['data'][0]) && array_unshift($data['data'], array_keys($data['data'][0]));

		array_unshift($data['data'], [$builder->getModel()->getTable(), $data['from']. '-'. $data['to'].'/'. $data['total'], date('Y-m-d h:i:s')]);

		return $data['data'];
	}
}
