<?php

namespace Addons\Elasticsearch\Scout\Console;

use Illuminate\Console\Command;
use Laravel\Scout\Events\ModelsImported;
use Illuminate\Contracts\Events\Dispatcher;

class ImportRangeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:import-range {model} {--min=0 : (number) the min ID, negative number is valid, 0 for the first ID} {--max=0: (number) the max ID, 0 for the last ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the given model of range into the search index';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function handle(Dispatcher $events)
    {
        $class = $this->argument('model');

        $model = new $class;

        $min = $this->option('min');
        $max = $this->option('max');

        (!is_numeric($min)) && $min = 0;
        (!is_numeric($max)) && $max = 0;

        $events->listen(ModelsImported::class, function ($event) use ($class) {
            $key = $event->models->last()->getKey();

            $this->line('<comment>Imported ['.$class.'] models up to ID:</comment> '.$key);
        });

        $model::makeAllSearchable($min, $max);

        $this->info('All ['.$class.'] records from '.$min.'-'.$max.' have been imported.');
    }
}
