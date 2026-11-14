<?php
namespace App\Http\Controllers\Api;
use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
class ObservabilityController extends Controller {
 public function health(){try{DB::connection()->getPdo();\Illuminate\Support\Facades\Redis::connection()->ping();return response()->json(['status'=>'UP','database'=>'OK','redis'=>'OK','queue'=>config('queue.default')]);}catch(\Throwable $e){return response()->json(['status'=>'DOWN','database'=>'ERROR','redis'=>'ERROR'],503);}}
 public function metrics(){ $counts=Notification::query()->select('status',DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total','status');$pending=Notification::where('status',NotificationStatus::PENDING)->count();$accepted=Notification::where('status',NotificationStatus::ACCEPTED)->count();$failed=Notification::where('status',NotificationStatus::FAILED)->count();$total=$accepted+$failed;return response()->json(['queue_depth'=>$pending,'counts'=>$counts,'success_rate_percent'=>$total?round($accepted/$total*100,2):0,'failure_rate_percent'=>$total?round($failed/$total*100,2):0,'average_latency_ms'=>round((float) (Notification::whereNotNull('provider_latency_ms')->avg('provider_latency_ms') ?? 0),2)]);}
}
