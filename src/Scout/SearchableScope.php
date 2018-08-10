<?php

namespace Addons\Elasticsearch\Scout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Laravel\Scout\Events\ModelsImported;
use Laravel\Scout\Events\ModelsFlushed;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class SearchableScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(EloquentBuilder $builder, Model $model)
    {
        //
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function extend(EloquentBuilder $builder)
    {
        $builder->macro('searchable', function (EloquentBuilder $builder, $chunk = null, bool $refresh = true) {
            $builder->chunk($chunk ?: config('scout.chunk.searchable', 500), function ($models) use ($refresh) {
                $models->filter->shouldBeSearchable()->searchable($refresh);

                event(new ModelsImported($models));
            });
        });

        $builder->macro('unsearchable', function (EloquentBuilder $builder, $chunk = null, bool $refresh = true) {
            $builder->chunk($chunk ?: config('scout.chunk.unsearchable', 500), function ($models) use ($refresh) {
                $models->unsearchable($refresh);

                event(new ModelsFlushed($models));
            });
        });
    }
}
