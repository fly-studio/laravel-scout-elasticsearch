# Laravel-Elasticsearch

An easy way to use the official Elastic Search 5.x~6.x client in your Laravel 5.

**Warning:** This Manual is for **addons/elasticsearch:3.0.0**, **Elasticsearch 6.x**. If you use the Elasticsearch 5.x, Please visit [2.0.1](https://github.com/fly-studio/laravel-scout-elasticsearch/tree/2.0.1)

* [Version](#version)
* [Difference](#difference)
* [Installation and Configuration](manuals/install.md)
* [Config](manuals/config.md)
* [Usage](manuals/usage.md)
* [Sync Database to ES](manuals/sync.md)
* [Logstash Supported](manuals/logstash.md)
* [Copyright and License](#copyright-and-license)

## Version

- addons/elasticsearch:1.0.2
  - Elasticsearch 5.x
  - Laravel/Scout 3.0
  - Laravel 5.1~5.5
- addons/elasticsearch:2.0.1
  - Elasticsearch 5.x
  - Laravel/Scout 3.0~4.0
  - Laravel 5.1~5.6
- addons/elasticsearch:3.0.0  - master
  - Elasticsearch 6.x
  - Laravel/Scout 4.0
  - Laravel 5.1~5.6


## Difference

Elasticsearch 6.0 removes(deprecats) the TYPE. (like database's table)

So We use the ES's index named `env('SCOUT_PREFIX').$mode->getTable()` for each table

eg:

.env
```
SCOUT_PREFIX = my_application_name-
```

Index name like:

```
my_application_name-users
my_application_name-roles
```


## Example

### Search like Laravel's Model

> This Builder will search in ES, not Database.

```
User::search('must')->where('name', 'admin')->whereIn('type', ['1', '2'])->get();

```

### Page

```
// page 1
User::search()->where(...)->paginate(25);

// page 4
User::search()->where(...)->paginate(25, ['*'], 'page', 4)
```

### Read/Modify the Elastic DSL JSON
```
User::search('must', function($elasticsearch, &$query){
    print_r($query); // Show the DSL JSON

    // ... edit the $query.

})->where(...)->get();

```


## Copyright and License

[elasticsearch](https://git.load-page.com/addons/elasticsearch)
was written by [Colin Viebrock](http://viebrock.ca), [Fly](https://www.load-page.com/manuals) and is released under the
[MIT License](LICENSE.md).

Copyright (c) 2016-2018
