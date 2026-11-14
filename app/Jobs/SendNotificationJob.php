<?php
namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\Notifications\WebhookNotificationProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public string $notificationId) {}

    public function handle(WebhookNotificationProvider $provider): void
    {
        $notification = Notification::find($this->notificationId);
        if (!$notification || $notification->status !== NotificationStatus::PENDING || ($notification->scheduled_at && $notification->scheduled_at->isFuture())) {
            return;
        }

        // Redis' Lua-backed throttle is shared across all worker processes/containers.
        Redis::throttle('notifications:channel:' . $notification->channel->value)
            ->block(0)
            ->allow((int) env('RATE_LIMIT_PER_CHANNEL', 100))
            ->every(1)
            ->then(
                function () use ($notification, $provider): void {
                    $claimed = Notification::whereKey($notification->id)
                        ->where('status', NotificationStatus::PENDING->value)
                        ->update(['status' => NotificationStatus::PROCESSING->value, 'attempts' => $notification->attempts + 1]);
                    if ($claimed !== 1) { return; }
                    $notification->refresh();

                    $startedAt = microtime(true);
                    $response = $provider->send($notification);
                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

                    if ($response->accepted) {
                        $notification->update([
                            'status' => NotificationStatus::ACCEPTED,
                            'provider_message_id' => $response->messageId,
                            'accepted_at' => now(),
                            'provider_latency_ms' => $latencyMs,
                            'last_error' => null,
                        ]);
                    } else {
                        $error = $response->error ?? 'Provider rejected request';
                        if ($notification->attempts < $notification->max_attempts) {
                            $notification->update([
                                'status' => NotificationStatus::PENDING,
                                'last_error' => $error,
                                'provider_latency_ms' => $latencyMs,
                            ]);
                            self::dispatch($notification->id)
                                ->onQueue($notification->priority->queue())
                                ->delay(now()->addSeconds(min(60, 2 ** $notification->attempts)));
                        } else {
                            $notification->update([
                                'status' => NotificationStatus::FAILED,
                                'failed_at' => now(),
                                'last_error' => $error,
                                'provider_latency_ms' => $latencyMs,
                            ]);
                        }
                    }

                    Log::info('notification.delivery_attempt', [
                        'notification_id' => $notification->id,
                        'batch_id' => $notification->batch_id,
                        'correlation_id' => $notification->correlation_id,
                        'channel' => $notification->channel->value,
                        'status' => $notification->fresh()->status->value,
                        'attempt' => $notification->attempts,
                        'latency_ms' => $latencyMs,
                    ]);
                },
                function () use ($notification): void {
                    self::dispatch($notification->id)
                        ->onQueue($notification->priority->queue())
                        ->delay(now()->addSecond());
                }
            );
    }

    public function failed(\Throwable $exception): void
    {
        $notification = Notification::find($this->notificationId);
        if ($notification && !in_array($notification->status, [NotificationStatus::ACCEPTED, NotificationStatus::CANCELLED], true)) {
            $notification->update([
                'status' => NotificationStatus::FAILED,
                'failed_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
        }
        Log::error('notification.job_failed', [
            'notification_id' => $this->notificationId,
            'exception' => $exception->getMessage(),
        ]);
    }

}
