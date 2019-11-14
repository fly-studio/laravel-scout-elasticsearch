<?php

namespace Addons\Elasticsearch;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Collection as ModelCollection;

class Collection extends BaseCollection {

	public function asDepthArray()
	{
		foreach($this->items as $k => $v)
		{
			$r = [];
			foreach($v as $key => $value)
				Arr::set($r, $key, $value);

			$this->items[$k] = $r;
		}

		return $this;
	}

	public function toModels($model, array $relations = [])
	{
		$model = is_string($model) ? $model : get_class($model);

		$results = [];

		foreach($this->items as $k => $v)
		{
			if (!($v instanceof Model))
			{
				$instance = new $model;

				foreach ($instance->getDates() as $key) {
					if (! isset($v[$key]) || empty($v[$key]))
						continue;

					$time = strtotime($v[$key]);

					$v[$key] = $time === false ? $v[$key] : Carbon::createFromTimestamp($time);
				}

				$instance->setRawAttributes(Arr::except($v, $relations));

				foreach($relations as $relation)
					$instance->setRelation($relation, Arr::get($v, $relation));

				$results[$k] = $instance;
			} else {
				$results[$k] = $v;
			}
		}

		return ModelCollection::make($results);
	}

	public function filterWithDB($model)
	{
		$model = is_string($model) ? $model : get_class($model);

		$keys = $this->pluck('id');

		if (empty($keys)) return new static();

		$instance = new $model;

		$models = $instance->newQuery()->whereIn($instance->getKeyName(), $keys)->get([$instance->getKeyName()])->modelKeys();

		return $this->filter(function($v){
			return in_array($v['id'], $models);
		});
	}

}
