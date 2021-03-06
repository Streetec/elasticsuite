<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile ElasticSuite to newer
 * versions in the future.
 *
 *
 * @category  Smile_Elasticsuite
 * @package   Smile\ElasticsuiteCore
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2018 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\ElasticsuiteCore\Test\Unit\Search\Adapter\Elasticsuite\Request\Aggregation;

use Smile\ElasticsuiteCore\Search\Adapter\Elasticsuite\Request\Aggregation\Builder as AggregationBuilder;
use Smile\ElasticsuiteCore\Search\Adapter\Elasticsuite\Request\Query\Builder as QueryBuilder;
use Smile\ElasticsuiteCore\Search\Adapter\Elasticsuite\Request\Aggregation\BuilderInterface;
use Smile\ElasticsuiteCore\Search\Request\QueryInterface;
use Smile\ElasticsuiteCore\Search\Request\BucketInterface;

/**
 * Search adapter aggregation builder test case.
 *
 * @category  Smile_Elasticsuite
 * @package   Smile\ElasticsuiteCore
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 */
class BuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test building a simple aggregation.
     *
     * @return void
     */
    public function testBuildSimpleAggregations()
    {
        $buckets = [
            $this->createBucket('aggregation1', 'bucketType'),
            $this->createBucket('aggregation2', 'bucketType'),
        ];

        $aggregations = $this->getAggregationBuilder()->buildAggregations($buckets);
        $this->assertCount(2, $aggregations);

        for ($i = 1; $i <= 2; $i++) {
            $aggregationName = sprintf('aggregation%s', $i);
            $this->processSimpleAggregartionAssertions($aggregationName, $aggregations);
        }
    }

    /**
     * Test building a nested aggregation.
     *
     * @return void
     */
    public function testBuildNestedAggregation()
    {
        $buckets = [$this->createNestedBucket('aggregation', 'bucketType')];
        $aggregations = $this->getAggregationBuilder()->buildAggregations($buckets);

        $aggregation  = $this->getAggregationByName($aggregations, 'aggregation');
        $this->assertArrayHasKey('nested', $aggregation);
        $this->assertArrayHasKey('path', $aggregation['nested']);
        $this->assertEquals('parent', $aggregation['nested']['path']);
        $this->assertCount(2, $aggregation);

        $aggregations = $this->getSubAggregations($aggregation);
        $this->processSimpleAggregartionAssertions('aggregation', $aggregations);
    }

    /**
     * Test building a nested filter aggregation.
     *
     * @return void
     */
    public function testBuildFilteredNestedAggregation()
    {
        $buckets = [$this->createFilteredNestedBucket('aggregation', 'bucketType')];
        $aggregations = $this->getAggregationBuilder()->buildAggregations($buckets);

        $aggregation  = $this->getAggregationByName($aggregations, 'aggregation');
        $this->assertArrayHasKey('nested', $aggregation);
        $this->assertArrayHasKey('path', $aggregation['nested']);
        $this->assertEquals('parent', $aggregation['nested']['path']);

        $aggregations = $this->getSubAggregations($aggregation);
        $aggregation  = $this->getAggregationByName($aggregations, 'aggregation');
        $this->assertArrayHasKey('filter', $aggregation);
        $this->assertEquals('query', $aggregation['filter']);
        $this->assertCount(2, $aggregation);

        $aggregations = $this->getSubAggregations($aggregation);
        $this->processSimpleAggregartionAssertions('aggregation', $aggregations);
    }

    /**
     * Test building a filtered aggregation.
     *
     * @return void
     */
    public function testBuildFilteredAggregation()
    {
        $buckets = [$this->createFilteredBucket('aggregation', 'bucketType')];
        $aggregations = $this->getAggregationBuilder()->buildAggregations($buckets);

        $aggregation  = $this->getAggregationByName($aggregations, 'aggregation');
        $this->assertArrayHasKey('filter', $aggregation);
        $this->assertEquals('query', $aggregation['filter']);
        $this->assertCount(2, $aggregation);

        $aggregations = $this->getSubAggregations($aggregation);
        $this->processSimpleAggregartionAssertions('aggregation', $aggregations);
    }

    /**
     * Test an exception is thrown when trying to build a bucket which is not handled by the builder.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage No builder found for aggregation type invalidBucketType.
     *
     * @return void
     */
    public function testBuildInvalidAggregation()
    {
        $buckets = [$this->createNestedBucket('aggregation', 'invalidBucketType')];
        $this->getAggregationBuilder()->buildAggregations($buckets);
    }

    /**
     * Prepare the aggregation builder used by the test case.
     *
     * @return AggregationBuilder
     */
    private function getAggregationBuilder()
    {
        $queryBuilder = $this->getQueryBuilder();
        $aggregationBuilderMock = $this->getMockBuilder(BuilderInterface::class)->getMock();

        $buildBucketCallback = function (BucketInterface $bucket) {
            return ['type' => $bucket->getType()];
        };
        $aggregationBuilderMock->method('buildBucket')->will($this->returnCallback($buildBucketCallback));

        return new AggregationBuilder($queryBuilder, ['bucketType' => $aggregationBuilderMock]);
    }

    /**
     * Create a simple bucket.
     *
     * @param string $name Bucket name.
     * @param string $type Bucket type.
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function createBucket($name, $type)
    {
        $bucket = $this->getMockBuilder(BucketInterface::class)->getMock();

        $bucket->method('getName')->will($this->returnValue($name));
        $bucket->method('getType')->will($this->returnValue($type));
        $bucket->method('getMetrics')->will($this->returnValue([]));

        return $bucket;
    }

    /**
     * Create a nested bucket.
     *
     * @param string $name Bucket name.
     * @param string $type Bucket type.
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function createNestedBucket($name, $type)
    {
        $bucket = $this->createBucket($name, $type);
        $bucket->method('isNested')->will($this->returnValue(true));
        $bucket->method('getNestedPath')->will($this->returnValue('parent'));

        return $bucket;
    }

    /**
     * Create a nested filtered bucket.
     *
     * @param string $name Bucket name.
     * @param string $type Bucket type.
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function createFilteredNestedBucket($name, $type)
    {
        $filter = $this->getMockBuilder(QueryInterface::class)->getMock();
        $bucket = $this->createBucket($name, $type);
        $bucket->method('isNested')->will($this->returnValue(true));
        $bucket->method('getNestedPath')->will($this->returnValue('parent'));
        $bucket->method('getNestedFilter')->will($this->returnValue($filter));

        return $bucket;
    }

    /**
     * Create a filtered bucket.
     *
     * @param string $name Bucket name.
     * @param string $type Bucket type.
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function createFilteredBucket($name, $type)
    {
        $filter = $this->getMockBuilder(QueryInterface::class)->getMock();
        $bucket = $this->createBucket($name, $type);
        $bucket->method('getFilter')->will($this->returnValue($filter));

        return $bucket;
    }

    /**
     * Mock a query builder.
     *
     * @return \Smile\ElasticsuiteCore\Search\Adapter\Elasticsuite\Request\Query\Builder
     */
    private function getQueryBuilder()
    {
        $queryBuilderMock = $this->getMockBuilder(QueryBuilder::class)->getMock();
        $queryBuilderMock->method('buildQuery')->will($this->returnValue('query'));

        return $queryBuilderMock;
    }

    /**
     * Run assertion on a simple bucket.
     *
     * @param string $aggregationName Aggregation name.
     * @param array  $aggregations    Parent aggregation.
     *
     * @return void
     */
    private function processSimpleAggregartionAssertions($aggregationName, $aggregations)
    {
        $this->assertArrayHasKey($aggregationName, $aggregations);
        $this->assertEquals(['type' => 'bucketType'], $aggregations[$aggregationName]);
    }

    /**
     * Return an aggregation by name.
     *
     * @param array  $aggregations    Aggregations.
     * @param string $aggregationName Aggregation name.
     *
     * @return array
     */
    private function getAggregationByName($aggregations, $aggregationName)
    {
        $this->assertArrayHasKey($aggregationName, $aggregations);

        return $aggregations[$aggregationName];
    }

    /**
     * Return all subaggregations of parent aggregation.
     *
     * @param string $aggregation   Parent aggregation.
     * @param number $expectedCount Expected number of subaggregation.
     *
     * @return string
     */
    private function getSubAggregations($aggregation, $expectedCount = 1)
    {
        $this->assertArrayHasKey('aggregations', $aggregation);
        $this->assertCount($expectedCount, $aggregation['aggregations']);

        return $aggregation['aggregations'];
    }
}
