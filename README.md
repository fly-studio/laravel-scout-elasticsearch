# Laravel-Elasticsearch

An easy way to use the official Elastic Search client in your Laravel 5.


* [Installation and Configuration](#installation-and-configuration)
* [Usage](#usage)
* [Copyright and License](#copyright-and-license)



## Installation and Configuration

Install the `addons/elasticsearch` package via composer:

```shell
composer require addons/elasticsearch
```

### Laravel

> If you used laravel 5.5+, execute `php artisan package:discover` only. no need to add the providers to `config/app.php`.

Add the service provider and facade to `config/app.php`:

```php
'providers' => [
    ...
    Laravel\Scout\ScoutServiceProvider::class,
    Addons\Elasticsearch\ServiceProvider::class,
]

'aliases' => [
    ...
    'Elasticsearch' => Addons\Elasticsearch\Facade::class,
]
```

`ScoutServiceProvider` must **Before** the `Addons\Elasticsearch\ServiceProvider`.

Put these to the `.env`

```
SCOUT_DRIVER=elasticsearch
SCOUT_PREFIX=
ELASTICSEARCH_INDEX=
ELASTICSEARCH_HOST=127.0.0.1
```

- ELASTICSEARCH_INDEX : your elastic's index
- ELASTICSEARCH_HOST: your elastic's host
- if you comment `#SCOUT_DRIVER` (add # at first), it will close the search.


Publish the configuration file:

```shell
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
php artisan vendor:publish --provider="Addons\Elasticsearch\ServiceProvider"
```

## Usage

### Elasticsearch Builder for laravel/scout

#### use in model

> the laravel/scout uses the laravel-queue to insert/update data to elastic, So you must enable your queue.

```php
//app\User.php

use Addons\Elasticsearch\Scout\Searchable;

class User extends Model {
    use Searchable;
}
```

#### CRUD of the record of Model 

Normally, your laravel/queue was enabled, and the database will automatic crud to the elasticsearch.
Of course, you can manual it.

```php
$book = Book::find(1);

$book->addToType(); // insert the record to es
$book->updateToType(); // update the record to es
$book->removeFromType(); // remove this record from es
$book->resetToType(); // remove and insert this record
$book->inType(); // exists in the type
```

#### Search example

```
User::search('must')->where('name', 'admin')->whereIn('type', ['1', '2'])->get();

//JSON
{
    "bool": {
        "must": [
            {
                "term": {
                    "name": {
                        "value": "admin"
                    }
                }
            },
            {
                "terms": {
                    "type": ["1", "2"]
                }
            }
        ]
    }
}
```

#### search(string $boolOccur = 'must', Closure $callback = null)

- $boolOccur [string]:  must|should|filter|must_not

  default: must

- $callback [Closure]:  null|Closure

  It'll call before get();

    - $elasticsearch [Object]:

     the elasticsearch's object

    - $query [array]:

     the builder's query to search

**Example**

```
User::search()->where(...)->get();

//JSON
{
    "bool": {
        "must": [
        ...
        ]
    }
}
```

#### Custom Query JSON

**Example:** edit the DSL JSON in search's callback

```
User::search('should', function($elasticsearch, &$query){
    print_r($query); // Show the DSL JSON

    // ... edit the $query.

})->where(...)->get();

//print JSON
{
    "bool": {
        "should": [
        ...
        ]
    }
}
```

#### where(string $column, string $operator, mixed $value, $options = [])

 - $column [string]:

    the field's name that you wanna to search.

 - $operator [string]:    term,=|terms,in|match,like|multi_match|range|prefix|common|wildcard|regexp|fuzzy|type|match_phrase|match_phrase_prefix|more_like_this|exists

    `https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl.html`

 - $value [mixed]:

    the value that you wanna to search.

 - $options [array]:

    the elasticseach DSL's parameters

**Example 1**

```php
User::search()
->where('name', '=', 'admin')
->where('gender', 'in', ['male', 'unknow'])
->where('title', 'like', 'Super')
->get();

//JSON
{
    "bool": {
        "must": [{
            "term": {
                "name": {
                    "value": "admin"
                }
            }
        }, {
            "terms": {
                "gender": ["male", "unknow"]
            }
        }, {
            "match": {
                "title": {
                    "query": "Super"
                }
            }
        }]
    }
}
```

**Example 2: with option**

```php
User::search()->where('created_at', 'range', [
    'gte' => '2000-01-01 00:00:00',
    'lt' => '2000-12-31 00:00:00',
], [
    'boost' => '2.0'
]);

//JSON
{
    "bool": {
        "must": [{
            "range": {
                "created_at": {
                    "gte": "2000-01-01 00:00:00",
                    "lt": "2000-12-31 00:00:00",
                    "boost": "2.0"
                }
            }
        }]
    }
}
```

#### where(string $column, mixed $value);

It equals to `where($field, '=', $value);`

#### where(Closure $nestedWhere, $boolOccur = 'must')

- $nestedWhere [Closure]

- $boolOccur [string]:  must|should|filter|must_not

  default: must

**Example**

```php
// like SQL: WHERE `name` = 'admin' AND (`gender` is null or `gender` = 'female')

User::search()
->where('name', 'admin')
->where(function($query){
    $query->where('gender', 'female')
    ->whereExists('gender');  // or where('gender' , 'exists', '');
}, 'should');

//JSON
{
    "bool": {
        "must": [{
            "term": {
                "name": {
                    "value": "admin"
                }
            }
        }, {
            "bool": {
                "should": [{
                    "term": {
                        "gender": {
                            "value": "female"
                        }
                    }
                }, {
                    "exists": {
                        "field": "gender"
                    }
                }]
            }
        }]
    }
}
```

#### whereNot(string $column, string $operator = null, mixed $value = null, array $options = [])

It equals to `where(Closure, 'must_not')`

**Example 1**

```php
User::search()->whereNot('name', 'admin')->where('gender', 'female')->get();

//JSON
{
    "bool": {
        "must": [{
            "bool": {
                "must_not": [{
                    "term": {
                        "name": {
                            "value": "admin"
                        }
                    }
                }]
            }
        }, {
            "term": {
                "gender": {
                    "value": "female"
                }
            }
        }]
    }
}
```

**Example 2**

```php
User::search()->where(function($query){
  $query->where('name', 'admin')
  ->where('gender', 'female');
}, 'must_not')->get();

// the same as above
User::search()->whereNot(function($query){
  $query->where('name', 'admin')
  ->where('gender', 'female');
})->get();

//JSON
{
    "bool": {
        "must": [{
            "bool": {
                "must_not": [{
                    "term": {
                        "name": {
                            "value": "admin"
                        }
                    }
                }, {
                    "term": {
                        "gender": {
                            "value": "female"
                        }
                    }
                }]
            }
        }]
    }
}
```

#### whereIn(string $column, array $value)

It equals to `where($column, 'in', $value)`

#### whereExists(string $column)

It equals to `where($column, 'exists', '')`

Like SQL: `where `$column` is null`

#### whereNotIn(string $column, array $value)

It equals to `whereNot($column, 'in', $value)`

#### whereAll(string $value)

Search $value in all fileds.

```
// JSON
{
	'match': {
		'_all': {
			'query': $value,
			'fuzziness': 1,
		}
	}
}
```


#### orderBy(string $column, string $direction = 'asc', $mode = null, $options = [])
- $column [string]:

  the column that you wanna orderBy

- $direction [string]: asc|desc

  default: asc

- $mode [string]: min|max|sum|avg|median

  default: null

- options [array]:

  default: []

Allows to add one or more sort on specific fields.
`https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-sort.html`

**Example**

```
User::search()->where(...)
->orderBy('created_at', 'desc')
->orderBy('updated_at', 'asc', 'avg')
->orderBy('xx', 'asc', null, [
  "nested_path" => "offer",
  "nested_filter" => [
    "term" => [ "color" => "blue" ]
  ]
])->get();

//JSON
"sort" : [
  {
    "created_at": {
      "mode": null,
      "order": "desc"
    }
  },
  {
    "updated_at": {
      "mode": "avg",
      "order": "asc",
    }
  },
  {
    "xx": {
      "mode": null,
      "order": "asc",
      "nested_path" : "offer",
      "nested_filter" : {
          "term" : { "color" : "blue" }
      }
    }
  }
]

```

#### get(array $columns = ['*'])

- $columns [array]:

  default: [*], all columns

Get data, like Model's get(), return Collection

**Example**

```php
Use::search()->where(...)->get();

Use::search()->where(...)->get(['name', 'gender', 'created_at']);
```

#### keys()

Make all records's id to an array

**Example**

```php
Use::search()->where(...)->keys();
```

#### paginate(int $perPage = null, array $columns = ['*'], string $pageName = 'page', int $page = null)

like Model's paginate

```
// page 1
User::search()->paginate(25);

// page 4
User::search()->paginate(25, ['*'], 'page', 4);
```

#### take(int $limit)

Like Model's take(1000);

#### setAggs(array $aggs)

- $aggs [array]:

  the aggs's array

**Example**

```php
$aggs = [
    'distinct_uid' =>[
        'cardinality' =>[
            'field' =>'user_id',
        ],
    ],
];
User::search()->setAggs($aggs)->aggregations('distinct_uid.value');
```

#### aggregations($key = null)

- $key [string]:

  default: null

  if defined, use `array_get($returnData, $key)` to find value

**Example**

```php
//return All aggs's data
User::search()->setAggs($aggs)->aggregations();

//return distinct_uid.value in aggs's data
User::search()->setAggs($aggs)->aggregations('distinct_uid.value');
```

### Logstash

It publish all Laravel's log to

- a JSON file `storage/logs/logstash-YYYY-MM-DD.log`

- redis server

Logstash can read and parse it;

`config/elasticsearch.php`
```php
'connections' => [
  'default' => [
    'logstashDriver' => null, //file, redis
  ]
]
```



## Copyright and License

[elasticsearch](https://git.load-page.com/addons/elasticsearch)
was written by [Colin Viebrock](http://viebrock.ca), [Fly](https://www.load-page.com/base/manual) and is released under the
[MIT License](LICENSE.md).

Copyright (c) 2016-2018
