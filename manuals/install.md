# Installation and Configuration

Install the `addons/elasticsearch` package via composer:

```shell
composer require addons/elasticsearch

# If you use Elasticsearch 5.x
composer require addons/elasticsearch:2.0.1
```

## Laravel

> If you used laravel 5.5+, execute `php artisan package:discover` only. no need to add these providers to `config/app.php`.

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
