<?php

namespace Illuminate\Tests\Bus;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Collection;
use Illuminate\Container\Container;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class BusPendingBatchTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_pending_batch_may_be_configured_and_dispatched()
    {
        $container = new Container;

        $pendingBatch = new PendingBatch($container, new Collection([$job = new class {
            use Batchable;
        }]));

        $pendingBatch = $pendingBatch->then(function () {
            //
        })->catch(function () {
            //
        })->allowFailures()->onConnection('test-connection')->onQueue('test-queue');

        $this->assertEquals('test-connection', $pendingBatch->connection());
        $this->assertEquals('test-queue', $pendingBatch->queue());
        $this->assertCount(1, $pendingBatch->thenCallbacks());
        $this->assertCount(1, $pendingBatch->catchCallbacks());

        $repository = m::mock(BatchRepository::class);
        $repository->shouldReceive('store')->once()->with($pendingBatch)->andReturn($batch = m::mock(stdClass::class));
        $batch->shouldReceive('add')->once()->with(m::type(Collection::class));

        $container->instance(BatchRepository::class, $repository);

        $pendingBatch->dispatch();
    }

    public function test_batch_is_deleted_from_storage_if_exception_thrown_during_batching()
    {
        $this->expectException(RuntimeException::class);

        $container = new Container;

        $pendingBatch = new PendingBatch($container, new Collection([new class {
        }]));

        $repository = m::mock(BatchRepository::class);

        $repository->shouldReceive('store')->once()->with($pendingBatch)->andReturn($batch = m::mock(stdClass::class));

        $batch->id = 'test-id';

        $batch->shouldReceive('add')->once()->andReturnUsing(function () {
            throw new RuntimeException('Failed to add jobs...');
        });

        $repository->shouldReceive('delete')->once()->with('test-id');

        $container->instance(BatchRepository::class, $repository);

        $pendingBatch->dispatch();
    }
}
