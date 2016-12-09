# Laravel-Elasticsearch

An easy way to use the official Elastic Search client in your Laravel 5.


* [Installation and Configuration](#installation-and-configuration)
* [Usage](#usage)
* [Bugs, Suggestions and Contributions](#bugs-suggestions-and-contributions)
* [Copyright and License](#copyright-and-license)



## Installation and Configuration

Install the `addons/elasticsearch` package via composer:

```shell
composer require addons/elasticsearch
```

### Laravel 

Add the service provider and facade to `config/app.php`:

```php
'providers' => [
    ...
    Addons\Elasticsearch\ServiceProvider::class,
]

'aliases' => [
    ...
    'Elasticsearch' => Addons\Elasticsearch\Facade::class,
]
```
    
Publish the configuration file:

```shell
php artisan vendor:publish --provider="Addons\Elasticsearch\ServiceProvider"
```

## Usage

The `Elasticsearch` facade is just an entry point into the ES client, so previously
you might have used:

```php
$data = [
    'body' => [
        'testField' => 'abc'
    ],
    'index' => 'my_index',
    'type' => 'my_type',
    'id' => 'my_id',
];

$client = ClientBuilder::create()->build();
$return = $client->index($data);
```

You can now replace those last two lines with simply:

```php
$return = Elasticsearch::index($data);
```

That will run the command on the default connection.  You can run a command on
any connection (see the `defaultConnection` setting and `connections` array in
the configuration file).

```php
$return = Elasticsearch::connection('connectionName')->index($data);
```

Please be noticed that you should not use Facade in Lumen. 
So, in Lumen - you should use IoC or get the ElasticSearch service object from the application.
```php
$elasticSearch = $this->app('elasticsearch');
```




## Copyright and License

[elasticsearch](https://git.load-page.com/addons/elasticsearch)
was written by [Colin Viebrock](http://viebrock.ca),[Fly Mirage](https://www.load-page.com/base/manual) and is released under the 
[MIT License](LICENSE.md).

Copyright (c) 2016