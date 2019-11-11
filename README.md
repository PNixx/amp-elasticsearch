# Amp ElasticSearch Client


`amphp/elasticsearch` is a non-blocking ElasticSearch client for use with the [`amp`](https://github.com/amphp/amp)
concurrency framework.

**Required PHP Version**

- PHP 7.1+

**Installation**

```bash
composer require amphp/elasticsearch
```

**Usage**

Just create a client instance and call its public methods which returns promises:

```php
Loop::run(function () {
  $client = new Amp\Elasticsearch\Client('http://my.elasticsearch.test:9200');
  yield $this->client->createIndex('myindex');
  $response = yield $this->client->indexDocument('myindex', '', ['testField' => 'abc']);
  echo $response['result']; // 'created'
});
```

See other usage examples in the [`tests/Integration/ClientTest.php`](./tests/Integration/ClientTest.php).

All client methods return an array representation of the ElasticSearch REST API responses in case of sucess or an `Amp\Elasticsearch\Error` in case of error.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.