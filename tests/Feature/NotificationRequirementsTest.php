<?php
namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function sms(string $key = ''): array
    {
        return array_filter([
            'recipient' => '+905551234567',
            'channel' => 'sms',
            'content' => 'Hello',
            'priority' => 'high',
            'idempotency_key' => $key,
        ], fn ($v) => $v !== '');
    }

    public function test_batch_creation_and_lookup(): void
    {
        Queue::fake();
        $response = $this->postJson('/api/notifications/batch', [
            'notifications' => [$this->sms('batch-one'), $this->sms('batch-two')],
        ])->assertStatus(202)->assertJsonPath('total', 2)->assertJsonPath('created_count', 2);
        $id = $response->json('batch_id');
        $this->getJson('/api/batches/'.$id)->assertOk()->assertJsonPath('data.total_count', 2)
            ->assertJsonCount(2, 'data.notifications');
        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_batch_replay_does_not_queue_duplicate(): void
    {
        Queue::fake();
        $this->postJson('/api/notifications', $this->sms('replay-me'))->assertCreated();
        $response = $this->postJson('/api/notifications/batch', [
            'notifications' => [$this->sms('replay-me'), $this->sms('fresh-key')],
        ])->assertStatus(202)->assertJsonPath('replayed_count', 1)->assertJsonPath('created_count', 1);
        $this->getJson('/api/batches/'.$response->json('batch_id'))
            ->assertJsonPath('data.total_count', 1)->assertJsonCount(1, 'data.notifications');
        $this->assertDatabaseCount('notifications', 2);
        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_duplicate_keys_in_same_batch_are_rejected(): void
    {
        $this->postJson('/api/notifications/batch', [
            'notifications' => [$this->sms('duplicate'), $this->sms('duplicate')],
        ])->assertUnprocessable()->assertJsonValidationErrors('notifications.1.idempotency_key');
    }

    public function test_filtering_and_pagination(): void
    {
        Queue::fake();
        $this->postJson('/api/notifications', $this->sms('list-sms'))->assertCreated();
        $this->postJson('/api/notifications', [
            'recipient' => 'hello@example.com', 'channel' => 'email', 'content' => 'Hi',
        ])->assertCreated();
        $this->getJson('/api/notifications?channel=sms&status=pending&per_page=1')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonCount(1, 'data');
    }

    public function test_cancel_pending_notification_and_prevent_second_cancel(): void
    {
        Queue::fake();
        $id = $this->postJson('/api/notifications', $this->sms('cancel-me'))
            ->assertCreated()->json('data.id');
        $this->postJson('/api/notifications/'.$id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson('/api/notifications/'.$id.'/cancel')->assertStatus(409);
    }

    public function test_email_requires_valid_recipient(): void
    {
        $this->postJson('/api/notifications', [
            'recipient' => 'invalid', 'channel' => 'email', 'content' => 'Hi',
        ])->assertUnprocessable()->assertJsonValidationErrors('recipient');
    }

    public function test_batch_of_one_thousand_is_accepted(): void
    {
        Queue::fake();
        $items = array_fill(0, 1000, $this->sms());
        $response = $this->postJson('/api/notifications/batch', ['notifications' => $items]);
        $response->assertStatus(202)->assertJsonPath('created_count', 1000);
        $this->assertDatabaseCount('notifications', 1000);
        Queue::assertPushed(SendNotificationJob::class, 1000);
    }
}
