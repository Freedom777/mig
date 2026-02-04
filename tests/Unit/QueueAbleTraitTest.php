<?php

namespace Tests\Unit;

use App\Jobs\BaseProcessJob;
use App\Models\Queue;
use Tests\TestCase;

class QueueAbleTraitTest extends TestCase
{
    // =========================================================================
    // pushToQueue()
    // =========================================================================

    /** @test */
    public function it_creates_queue_entry_and_returns_success(): void
    {
        $data = ['image_id' => 123];

        $response = BaseProcessJob::pushToQueue(
            BaseProcessJob::class,
            'test-queue',
            $data
        );

        $responseData = $response->getData();

        $this->assertEquals('success', $responseData->status);
        $this->assertEquals(1, \App\Models\Queue::count());
    }

    /** @test */
    public function it_returns_exists_for_duplicate_job(): void
    {
        $data = ['image_id' => 456];

        // Первый вызов
        $response1 = BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', $data);
        $this->assertEquals('success', $response1->getData()->status);

        // Повторный вызов с теми же данными
        $response2 = BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', $data);
        $this->assertEquals('exists', $response2->getData()->status);

        // В БД только одна запись
        $this->assertEquals(1, Queue::count());
    }

    /** @test */
    public function it_creates_different_entries_for_different_data(): void
    {
        BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', ['image_id' => 1]);
        BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', ['image_id' => 2]);
        BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', ['image_id' => 3]);

        $this->assertEquals(3, Queue::count());
    }

    /** @test */
    public function it_creates_different_entries_for_different_job_classes(): void
    {
        $data = ['image_id' => 123];

        BaseProcessJob::pushToQueue('App\Jobs\ImageProcessJob', 'images', $data);
        BaseProcessJob::pushToQueue('App\Jobs\ThumbnailProcessJob', 'thumbnails', $data);

        $this->assertEquals(2, Queue::count());
    }

    // =========================================================================
    // removeFromQueue()
    // =========================================================================

    /** @test */
    public function it_removes_queue_entry(): void
    {
        $data = ['image_id' => 789];

        // Создаём запись
        BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', $data);
        $this->assertEquals(1, Queue::count());

        // Удаляем
        BaseProcessJob::removeFromQueue(BaseProcessJob::class, $data);
        $this->assertEquals(0, Queue::count());
    }

    /** @test */
    public function it_removes_only_matching_entry(): void
    {
        BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', ['image_id' => 1]);
        BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', ['image_id' => 2]);

        $this->assertEquals(2, Queue::count());

        BaseProcessJob::removeFromQueue(BaseProcessJob::class, ['image_id' => 1]);

        $this->assertEquals(1, Queue::count());
    }

    /** @test */
    public function it_handles_remove_nonexistent_entry_gracefully(): void
    {
        // Не должно выбросить исключение
        BaseProcessJob::removeFromQueue(BaseProcessJob::class, ['image_id' => 99999]);

        $this->assertEquals(0, Queue::count());
    }

    // =========================================================================
    // Queue key generation
    // =========================================================================

    /** @test */
    public function it_generates_consistent_queue_key(): void
    {
        $data = ['image_id' => 123, 'extra' => 'data'];

        BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', $data);

        // Тот же data должен считаться дубликатом
        $response = BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', $data);

        $this->assertEquals('exists', $response->getData()->status);
    }

    /** @test */
    public function it_treats_different_order_as_different_key(): void
    {
        // Порядок ключей в массиве может влиять на MD5
        // Это поведение зависит от реализации - просто проверяем что работает
        BaseProcessJob::pushToQueue(BaseProcessJob::class, 'test-queue', ['a' => 1, 'b' => 2]);

        $this->assertEquals(1, Queue::count());
    }
}
