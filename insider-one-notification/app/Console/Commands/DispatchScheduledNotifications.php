<?php
namespace App\Console\Commands;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Console\Command;
class DispatchScheduledNotifications extends Command {
 protected $signature='notifications:dispatch-scheduled';
 protected $description='Dispatch due scheduled notifications to their priority queues';
 public function handle(): int {
  Notification::where('status',NotificationStatus::PENDING)->whereNotNull('scheduled_at')->where('scheduled_at','<=',now())->orderBy('scheduled_at')->chunkById(200,function($items){foreach($items as $n){SendNotificationJob::dispatch($n->id)->onQueue($n->priority->queue());$n->update(['scheduled_at'=>null]);}});
  return self::SUCCESS;
 }
}
