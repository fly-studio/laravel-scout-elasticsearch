<?php

namespace Addons\Elasticsearch;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Collection as ModelCollection;

class Collection extends BaseCollection {

	protected $model = null;

	public function setModelName($model)
	{
		$this->model = is_string($model) ? $model : get_class($model);
		return $this;
	}

	public function asModels() : ModelCollection
	{
		$collection = $this->map(function($v) {
			if ($v instanceof Model)
				return $v;

			$model = new $this->model;

			foreach ($model->getDates() as $key) {
				if (! isset($v[$key]) || empty($v[$key]))
					continue;

				$time = strtotime($v[$key]);

				$v[$key] = $time === false ? $v[$key] : Carbon::createFromTimestamp($time);
			}

			$raw = [];
			foreach($v as $key => $value)
				if (strpos($key, '.') === false)
					$raw[$key] = $value;

			$model->setRawAttributes($raw);

			$data = [];

			foreach(array_except($v, array_keys($raw)) as $key => $value)
				array_set($data, $key, $value);

			foreach($data as $key => $value)
				$model->setRelation($key, $value);

			return $model;
		});

		return ModelCollection::make($collection->all());
	}

	public function existsInDB(): Collection
	{
		$keys = $this->pluck('id');

		if (empty($keys)) return new static();

		$model = new $this->model;

		$models = $model->newQuery()->whereIn($model->getKeyName(), $keys)->get([$model->getKeyName()])->modelKeys();

		return $this->filter(function($v){
			return in_array($v['id'], $models);
		});
	}

	public function load($relations)
	{
		return $this->asModels()->load($relations);
	}

}
