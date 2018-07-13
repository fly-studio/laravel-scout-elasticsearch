<?php

namespace Addons\Elasticsearch\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;

class MapIndexCommand extends Command {

	protected $signature = 'es:mapping {index_name : The Index Name of Mapping}
			{--t|template= : The index template of Absolute Path}
			{--f|force : If exists the index, delete it first}
			{--pinyin=default : eg. title,subtitle : What fields supported Chinese PinYin}
			{--ik=default :  eg. content,description : What fields supported Chinese words cut with IK}
			{--ik-pinyin=default :  eg. content,description : What fields supported Chinese Pinyin / IK}
			';

	protected $description = 'Create an ElasticSearch Index with mapping a Template.';

	public function handle(Dispatcher $events)
	{

		if (env('SCOUT_DRIVER') != 'elasticsearch')
			return $this->error('Enable SCOUT_DRIVER=elasticsearch in .env');


		$index_name = $this->argument('index_name');
		$template = $this->option('template');
		$force = $this->option('force');
		$pinyin = $this->option('pinyin');
		$ik = $this->option('ik');
		$ip = $this->option('ik-pinyin');

		if (empty($template))
			$template = __DIR__.'/../stubs/default.json';

		$params = json_decode(file_get_contents($template), true);

		$params = array_merge_recursive($params, $this->makePy($pinyin), $this->makeIk($ik), $this->makeIkPy($ip));

		$e = app('elasticsearch');
		if ($e->indices()->exists(['index' => $index_name]))
		{
			if ($force)
			{
				$e->indices()->delete(['index' => $index_name]);

			} else {
				return $this->error('The Index named: ['.$index_name.'] is exists, use --force to Force creating');
			}
		}

		$params['index'] = $index_name;

		$e->indices()->create($params);

		$this->info('Create Index Success.');
	}

	private function makePy($pinyin)
	{
		$params = [];
		if ($pinyin != 'default')
		{
			$pinyin = empty($pinyin) ? '^.*?(name|title|alias)$' : '^('.str_replace([',', ' '], ['|', ''], $pinyin).')$';
			$params = $this->getPy();
			$params['body']['mappings']['_default_']['dynamic_templates'][0]['pinyin']['match'] = $pinyin;
		}
		return $params;
	}

	private function makeIk($ik)
	{
		$params = [];
		if ($ik != 'default')
		{
			$ik = empty($ik) ? '^.*?(content|text|description)$' : '^('.str_replace([',', ' '], ['|', ''], $ik).')$';
			$params = $this->getIk();
			$params['body']['mappings']['_default_']['dynamic_templates'][0]['ik']['match'] = $ik;
		}
		return $params;
	}

	private function makeIkPy($ip)
	{
		$params = [];
		if ($ip != 'default')
		{
			if ($this->option('pinyin') == 'default') // no pinyin
			{
				$pyParams = $this->getPy();
				unset($pyParams['body']['mappings']);
				$params = array_merge_recursive($params, $pyParams);
			}

			if ($this->option('ik') == 'default') // no ik
			{
				$ikParams = $this->getIk();
				unset($ikParams['body']['mappings']);
				$params = array_merge_recursive($params, $ikParams);
			}

			$ip = empty($ip) ? '^.*?(content|text|description)$' : '^('.str_replace([',', ' '], ['|', ''], $ip).')$';
			$ipParams = $this->getIkPy();
			$ipParams['body']['mappings']['_default_']['dynamic_templates'][0]['ik_pinyin']['match'] = $ip;
			$params = array_merge_recursive($params, $ipParams);
		}
		return $params;
	}

	private function getPy()
	{
		return json_decode(file_get_contents(__DIR__.'/../stubs/pinyin.json'), true);
	}

	private function getIk()
	{
		return json_decode(file_get_contents(__DIR__.'/../stubs/ik.json'), true);
	}

	private function getIkPy()
	{
		return json_decode(file_get_contents(__DIR__.'/../stubs/ik-pinyin.json'), true);
	}
}
