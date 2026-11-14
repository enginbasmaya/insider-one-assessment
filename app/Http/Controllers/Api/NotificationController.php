<?php
namespace App\Http\Controllers\Api;
use App\Enums\{NotificationChannel,NotificationPriority,NotificationStatus};
use App\Http\Controllers\Controller;
use App\Http\Requests\{StoreNotificationRequest,StoreBatchRequest};
use App\Models\{Notification,NotificationBatch};
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class NotificationController extends Controller {
 public function store(StoreNotificationRequest $request, NotificationDispatcher $dispatcher){$data=$request->validated(); if(!empty($data['idempotency_key']) && $existing=Notification::where('idempotency_key',$data['idempotency_key'])->first()) return response()->json(['data'=>$existing,'idempotent_replay'=>true],200); $n=DB::transaction(fn()=>Notification::create($this->payload($data,null))); if(!$n->scheduled_at) $dispatcher->dispatch($n); return response()->json(['data'=>$n,'correlation_id'=>$n->correlation_id],201);}
 public function batch(StoreBatchRequest $request, NotificationDispatcher $dispatcher)
 {
  $items=$request->validated()['notifications'];
  $batchId=(string) Str::uuid();
  $created=DB::transaction(function() use($items,$batchId){
   NotificationBatch::create(['id'=>$batchId,'total_count'=>0]);
   $rows=[];
   foreach($items as $item){
    // Replayed keys belong to their original batch and must not be re-dispatched.
    if(!empty($item['idempotency_key'])){
     $existing=Notification::where('idempotency_key',$item['idempotency_key'])->first();
     if($existing){$rows[]=['notification'=>$existing,'replayed'=>true];continue;}
    }
    $rows[]=['notification'=>Notification::create($this->payload($item,$batchId)),'replayed'=>false];
   }
   NotificationBatch::whereKey($batchId)->update(['total_count'=>count(array_filter($rows,fn($entry)=>!$entry['replayed']))]);
   return $rows;
  });
  foreach($created as $entry){
   $n=$entry['notification'];
   if(!$entry['replayed'] && !$n->scheduled_at) $dispatcher->dispatch($n);
  }
  return response()->json([
   'batch_id'=>$batchId,
   'total'=>count($created),
   'created_count'=>count(array_filter($created,fn($entry)=>!$entry['replayed'])),
   'replayed_count'=>count(array_filter($created,fn($entry)=>$entry['replayed'])),
   'notifications'=>array_map(fn($entry)=>$entry['notification'],$created),
  ],202);
 }
 private function payload(array $d,?string $batch):array{return ['batch_id'=>$batch,'recipient'=>$d['recipient'],'channel'=>$d['channel'],'content'=>$d['content'],'priority'=>$d['priority']??'normal','status'=>'pending','idempotency_key'=>$d['idempotency_key']??null,'scheduled_at'=>$d['scheduled_at']??null,'max_attempts'=>3,'attempts'=>0,'correlation_id'=>(string)Str::uuid()];}
 public function index(Request $request){$filters=$request->validate(['status'=>'sometimes|in:pending,processing,accepted,failed,cancelled','channel'=>'sometimes|in:sms,email,push','from'=>'sometimes|date','to'=>'sometimes|date','per_page'=>'sometimes|integer|min:1|max:100']);return Notification::filter($filters)->latest()->paginate($filters['per_page']??25);}
 public function show(string $id){$n=Notification::find($id);if(!$n)return response()->json(['message'=>'Notification not found'],404);return response()->json(['data'=>$n]);}
 public function batchShow(string $batchId){$batch=NotificationBatch::with('notifications')->find($batchId);if(!$batch)return response()->json(['message'=>'Batch not found'],404);return response()->json(['data'=>$batch]);}
 public function cancel(string $id,NotificationDispatcher $dispatcher){$n=Notification::find($id);if(!$n)return response()->json(['message'=>'Notification not found'],404);if(!$dispatcher->cancel($n))return response()->json(['message'=>'Only pending notifications can be cancelled','status'=>$n->status->value],409);return response()->json(['data'=>$n->fresh()]);}
}
