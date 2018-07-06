
# Logstash

## Config

`config/elasticsearch.php`

```php
'connections' => [

  'default' => [
    'logstashDriver' => null, //file, redis
  ],

]
```

## Usage

the driver that your defined publishs all Laravel's log

- a JSON file `storage/logs/logstash-YYYY-MM-DD.log`

- redis server



Logstash can read these From `logstash-YYYY-MM-DD.log` OR `redis`;
