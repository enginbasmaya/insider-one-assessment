<?php
namespace App\Services;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Support\Facades\Cache;
class NotificationDispatcher {
 public function dispatch(Notification $notification): void {
  if($notification->status!==NotificationStatus::PENDING) return;
  $queue=$notification->priority->queue();
  SendNotificationJob::dispatch($notification->id)->onQueue($queue);
 }
 public function cancel(Notification $notification): bool {
  return Notification::whereKey($notification->id)->where('status',NotificationStatus::PENDING->value)->update(['status'=>NotificationStatus::CANCELLED->value]) === 1;
 }
}
