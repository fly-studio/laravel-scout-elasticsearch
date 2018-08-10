<?php

namespace Addons\Elasticsearch\Scout;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;

class MakeSearchable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The models to be made searchable.
     *
     * @var \Illuminate\Database\Eloquent\Collection
     */
    public $models;
    public $refresh;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function __construct($models, bool $refresh = true)
    {
        $this->models = $models;
        $this->refresh = $refresh;
    }

    /**
     * Handle the job.
     *
     * @return void
     */
    public function handle()
    {
        if (count($this->models) === 0) {
            return;
        }

        $this->models->loadMissing($this->models->first()->searchableWith());

        $this->models->first()->searchableUsing()->update($this->models, $this->refresh);
    }
}
