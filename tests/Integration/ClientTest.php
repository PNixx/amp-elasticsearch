<?php

declare(strict_types=1);

namespace Amp\Elasticsearch\Tests\Integration;

use Amp\Elasticsearch\Client;
use Amp\Elasticsearch\Error;
use Amp\Promise;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    const TEST_INDEX = 'test_index';
    const DEFAULT_ES_URL = 'http://127.0.0.1:9200';

    /**
     * @var Client
     */
    private $client;

    protected function setUp(): void
    {
        $esUrl = getenv('ES_URL') ?: self::DEFAULT_ES_URL;
        $this->client = new Client($esUrl);
        $indices = Promise\wait($this->client->catIndices());
        foreach ($indices as $index) {
            Promise\wait($this->client->deleteIndex($index['index']));
        }
    }

    public function testCreateIndex(): void
    {
        $response = Promise\wait($this->client->createIndex(self::TEST_INDEX));
        $this->assertIsArray($response);
        $this->assertTrue($response['acknowledged']);
        $this->assertEquals(self::TEST_INDEX, $response['index']);
    }

    public function testIndicesExistsShouldThrow404ErrorIfIndexDoesNotExists(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionCode(404);
        Promise\wait($this->client->existsIndex(self::TEST_INDEX));
    }

    public function testIndicesExistsShouldNotThrowAnErrorIfIndexExists(): void
    {
        Promise\wait($this->client->createIndex(self::TEST_INDEX));
        $response = Promise\wait($this->client->existsIndex(self::TEST_INDEX));
        $this->assertNull($response);
    }

    public function testDocumentsIndex(): void
    {
        $response = Promise\wait($this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc']));
        $this->assertIsArray($response);
        $this->assertEquals(self::TEST_INDEX, $response['_index']);
    }

    public function testDocumentsIndexWithAutomaticIdCreation(): void
    {
        $response = Promise\wait($this->client->indexDocument(self::TEST_INDEX, '', ['testField' => 'abc']));
        $this->assertIsArray($response);
        $this->assertEquals(self::TEST_INDEX, $response['_index']);
        $this->assertEquals('created', $response['result']);
    }

    public function testDocumentsExistsShouldThrowA404ErrorIfDocumentDoesNotExists(): void
    {
        Promise\wait($this->client->createIndex(self::TEST_INDEX));
        $this->expectException(Error::class);
        $this->expectExceptionCode(404);
        Promise\wait($this->client->existsDocument(self::TEST_INDEX, 'not-existent-doc'));
    }

    public function testDocumentsExistsShouldNotThrowAnErrorIfDocumentExists(): void
    {
        Promise\wait($this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc']));
        $response = Promise\wait($this->client->existsDocument(self::TEST_INDEX, 'my_id'));
        $this->assertNull($response);
    }

    public function testDocumentsGet(): void
    {
        Promise\wait($this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc']));
        $response = Promise\wait($this->client->getDocument(self::TEST_INDEX, 'my_id'));
        $this->assertIsArray($response);
        $this->assertTrue($response['found']);
        $this->assertEquals('my_id', $response['_id']);
        $this->assertEquals('abc', $response['_source']['testField']);
    }

    public function testDocumentsGetWithOptions(): void
    {
        Promise\wait($this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc']));
        $response = Promise\wait($this->client->getDocument(self::TEST_INDEX, 'my_id', ['_source' => 'false']));
        $this->assertIsArray($response);
        $this->assertTrue($response['found']);
        $this->assertArrayNotHasKey('_source', $response);
    }

    public function testDocumentsGetWithOnlySource(): void
    {
        Promise\wait($this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc']));
        $response = Promise\wait($this->client->getDocument(self::TEST_INDEX, 'my_id', [], '_source'));
        $this->assertIsArray($response);
        $this->assertEquals('abc', $response['testField']);
    }

    public function testDocumentsDelete(): void
    {
        Promise\wait($this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc']));
        $response = Promise\wait($this->client->deleteDocument(self::TEST_INDEX, 'my_id'));
        $this->assertIsArray($response);
        $this->assertEquals('deleted', $response['result']);
    }

    public function testUriSearchOneIndex(): void
    {
        Promise\wait(
            $this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc'], ['refresh' => 'true'])
        );
        $response = Promise\wait($this->client->uriSearchOneIndex(self::TEST_INDEX, 'testField:abc'));
        $this->assertIsArray($response);
        $this->assertCount(1, $response['hits']['hits']);
    }

    public function testUriSearchAllIndices(): void
    {
        Promise\wait(
            $this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc'], ['refresh' => 'true'])
        );
        $response = Promise\wait($this->client->uriSearchAllIndices('testField:abc'));
        $this->assertIsArray($response);
        $this->assertCount(1, $response['hits']['hits']);
    }

    public function testUriSearchManyIndices(): void
    {
        Promise\wait(
            $this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc'], ['refresh' => 'true'])
        );
        $response = Promise\wait($this->client->uriSearchManyIndices([self::TEST_INDEX], 'testField:abc'));
        $this->assertIsArray($response);
        $this->assertCount(1, $response['hits']['hits']);
    }

    public function testStatsIndexWithAllMetric(): void
    {
        Promise\wait(
            $this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc'], ['refresh' => 'true'])
        );
        $response = Promise\wait($this->client->statsIndex(self::TEST_INDEX));
        $this->assertEquals(1, $response['indices'][self::TEST_INDEX]['total']['indexing']['index_total']);
    }

    public function testStatsIndexWithDocsMetric(): void
    {
        Promise\wait(
            $this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc'], ['refresh' => 'true'])
        );
        $response = Promise\wait($this->client->statsIndex(self::TEST_INDEX, 'docs'));
        $this->assertArrayNotHasKey('indexing', $response['indices'][self::TEST_INDEX]['total']);
        $this->assertEquals(1, $response['indices'][self::TEST_INDEX]['total']['docs']['count']);
    }

    public function testCatIndices(): void
    {
        Promise\wait(
            $this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc'], ['refresh' => 'true'])
        );
        $response = Promise\wait($this->client->catIndices());
        $this->assertCount(1, $response);
        $this->assertEquals(self::TEST_INDEX, $response[0]['index']);
    }

    public function testCatIndicesWithoutIndices(): void
    {
        $response = Promise\wait($this->client->catIndices());
        $this->assertCount(0, $response);
    }

    public function testCatIndicesWithSpecificIndex(): void
    {
        Promise\wait(
            $this->client->indexDocument(self::TEST_INDEX, 'my_id', ['testField' => 'abc'], ['refresh' => 'true'])
        );
        Promise\wait(
            $this->client->indexDocument('another_index', 'my_id', ['testField' => 'abc'], ['refresh' => 'true'])
        );
        $response = Promise\wait($this->client->catIndices(self::TEST_INDEX));
        $this->assertCount(1, $response);
        $this->assertEquals(self::TEST_INDEX, $response[0]['index']);
    }

    public function testCatHealth(): void
    {
        $response = Promise\wait($this->client->catHealth());
        $this->assertCount(1, $response);
        $this->assertEquals('docker-cluster', $response[0]['cluster']);
    }
}
