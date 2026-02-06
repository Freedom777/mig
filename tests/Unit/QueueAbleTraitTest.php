<?php

namespace Tests\Unit;

use App\Jobs\ImageProcessJob;
use App\Models\Queue;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Тесты QueueAbleTrait
 * 
 * Используем Bus::fake() чтобы dispatch() не выполнял job реально.
 * Это позволяет тестировать логику дедупликации (таблица queues)
 * без фактического выполнения jobs.
 */
class QueueAbleTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Перехватываем все dispatch вызовы — jobs не выполняются реально
        Bus::fake();
    }

    // =========================================================================
    // pushToQueue()
    // =========================================================================

    /** @test */
    public function it_creates_queue_entry_and_returns_success(): void
    {
        $data = ['image_id' => 123];

        $response = ImageProcessJob::pushToQueue(
            ImageProcessJob::class,
            'test-queue',
            $data
        );

        $responseData = $response->getData();

        $this->assertEquals('success', $responseData->status);
        $this->assertEquals(1, Queue::count());
        
        // Проверяем что job был отправлен
        Bus::assertDispatched(ImageProcessJob::class);
    }

    /** @test */
    public function it_returns_exists_for_duplicate_job(): void
    {
        $data = ['image_id' => 456];

        // Первый вызов
        $response1 = ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', $data);
        $this->assertEquals('success', $response1->getData()->status);

        // Повторный вызов с теми же данными
        $response2 = ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', $data);
        $this->assertEquals('exists', $response2->getData()->status);

        // В БД только одна запись
        $this->assertEquals(1, Queue::count());
    }

    /** @test */
    public function it_creates_different_entries_for_different_data(): void
    {
        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', ['image_id' => 1]);
        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', ['image_id' => 2]);
        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', ['image_id' => 3]);

        $this->assertEquals(3, Queue::count());
    }

    /** @test */
    public function it_creates_different_entries_for_different_job_classes(): void
    {
        $data = ['image_id' => 123];

        ImageProcessJob::pushToQueue('App\Jobs\ImageProcessJob', 'images', $data);
        ImageProcessJob::pushToQueue('App\Jobs\ThumbnailProcessJob', 'thumbnails', $data);

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
        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', $data);
        $this->assertEquals(1, Queue::count());

        // Удаляем
        ImageProcessJob::removeFromQueue(ImageProcessJob::class, $data);
        $this->assertEquals(0, Queue::count());
    }

    /** @test */
    public function it_removes_only_matching_entry(): void
    {
        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', ['image_id' => 1]);
        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', ['image_id' => 2]);

        $this->assertEquals(2, Queue::count());

        ImageProcessJob::removeFromQueue(ImageProcessJob::class, ['image_id' => 1]);

        $this->assertEquals(1, Queue::count());
    }

    /** @test */
    public function it_handles_remove_nonexistent_entry_gracefully(): void
    {
        // Не должно выбросить исключение
        ImageProcessJob::removeFromQueue(ImageProcessJob::class, ['image_id' => 99999]);

        $this->assertEquals(0, Queue::count());
    }

    // =========================================================================
    // existsInQueue()
    // =========================================================================

    /** @test */
    public function it_checks_if_entry_exists_in_queue(): void
    {
        $data = ['image_id' => 111];

        $this->assertFalse(ImageProcessJob::existsInQueue(ImageProcessJob::class, $data));

        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', $data);

        $this->assertTrue(ImageProcessJob::existsInQueue(ImageProcessJob::class, $data));
    }

    // =========================================================================
    // Queue key generation
    // =========================================================================

    /** @test */
    public function it_generates_consistent_queue_key(): void
    {
        $data = ['image_id' => 123, 'extra' => 'data'];

        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', $data);

        // Тот же data должен считаться дубликатом
        $response = ImageProcessJob::pushToQueue(ImageProcessJob::class, 'test-queue', $data);

        $this->assertEquals('exists', $response->getData()->status);
    }

    /** @test */
    public function it_generates_different_keys_for_different_classes(): void
    {
        $data = ['image_id' => 100];

        // Один и тот же data для разных классов = разные ключи
        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'q1', $data);
        ImageProcessJob::pushToQueue('App\Jobs\ThumbnailProcessJob', 'q2', $data);

        $this->assertEquals(2, Queue::count());
    }

    /** @test */
    public function key_includes_class_name_in_hash(): void
    {
        $data = ['image_id' => 200];

        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'queue', $data);
        
        // Та же data но другой класс — должна создаться новая запись
        $response = ImageProcessJob::pushToQueue('App\Jobs\MetadataProcessJob', 'queue', $data);
        
        $this->assertEquals('success', $response->getData()->status);
        $this->assertEquals(2, Queue::count());
    }

    /** @test */
    public function it_treats_different_key_order_as_different_hash(): void
    {
        // JSON сериализация сохраняет порядок ключей
        // Разный порядок = разные хеши (текущее поведение)
        $data1 = ['image_id' => 1, 'type' => 'full'];
        $data2 = ['type' => 'full', 'image_id' => 1];

        ImageProcessJob::pushToQueue(ImageProcessJob::class, 'queue', $data1);
        $response = ImageProcessJob::pushToQueue(ImageProcessJob::class, 'queue', $data2);

        // Порядок ключей влияет на MD5 хеш — это разные записи
        $this->assertEquals('success', $response->getData()->status);
        $this->assertEquals(2, Queue::count());
    }
}
