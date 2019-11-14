<?php

namespace Addons\Elasticsearch\Contracts;

use Addons\Elasticsearch\ApiTrait;
use Addons\Elasticsearch\Scout\Builder;

abstract class EsRepository {

	use ApiTrait;

	public function duplicatedCount(Builder $builder, string $groupByField, ...$params)
	{
		$total = $builder->count();

		foreach ($params as $param)
			$builder->where($param[0], $param[1], $param[2]);

		return $builder->setAggs([
			"duplicate_aggs" => [
				"terms" => [
					"field" => $groupByField,
					"size" => $total,
				],
			],
			"duplicate_bucketcount" => [
				"stats_bucket" => [
					"buckets_path" => "duplicate_aggs._count",
				],
			],
		])->aggregations('duplicate_bucketcount.count');
	}

}
