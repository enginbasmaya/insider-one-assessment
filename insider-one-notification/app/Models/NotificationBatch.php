<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class NotificationBatch extends Model { protected static function booted(): void { static::creating(function (self $model) { $model->id ??= (string) Str::uuid(); }); } protected $fillable=['id','total_count','metadata']; public $incrementing=false; protected $keyType='string'; protected function casts(): array { return ['metadata'=>'array']; } public function notifications(){return $this->hasMany(Notification::class,'batch_id','id');} }
