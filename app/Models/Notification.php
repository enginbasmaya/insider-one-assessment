<?php
namespace App\Models;
use App\Enums\{NotificationChannel,NotificationPriority,NotificationStatus};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class Notification extends Model {
 public $incrementing = false; protected $keyType = 'string';
 protected static function booted(): void { static::creating(function (self $model) { $model->id ??= (string) Str::uuid(); }); }
 protected $fillable=['batch_id','recipient','channel','content','priority','status','idempotency_key','provider_message_id','attempts','max_attempts','scheduled_at','accepted_at','failed_at','last_error','correlation_id'];
 protected function casts(): array { return ['channel'=>NotificationChannel::class,'priority'=>NotificationPriority::class,'status'=>NotificationStatus::class,'scheduled_at'=>'datetime','accepted_at'=>'datetime','failed_at'=>'datetime']; }
 public function scopeFilter($query,array $filters) { foreach(['status','channel'] as $key) if(!empty($filters[$key])) $query->where($key,$filters[$key]); if(!empty($filters['from'])) $query->whereDate('created_at','>=',$filters['from']); if(!empty($filters['to'])) $query->whereDate('created_at','<=',$filters['to']); return $query; }
}
