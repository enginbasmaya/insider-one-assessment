<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendNotificationJob;
class NotificationApiTest extends TestCase {
 use RefreshDatabase;
 public function test_can_create_and_queue_notification():void{Queue::fake();$r=$this->postJson('/api/notifications',['recipient'=>'+905551234567','channel'=>'sms','content'=>'Hello','priority'=>'high']);$r->assertCreated()->assertJsonPath('data.status','pending');Queue::assertPushed(SendNotificationJob::class);}
 public function test_rejects_invalid_channel():void{$this->postJson('/api/notifications',['recipient'=>'x','channel'=>'fax','content'=>'x'])->assertUnprocessable();}
 public function test_batch_limit_is_one_thousand():void{$items=[];for($i=0;$i<1001;$i++)$items[]=['recipient'=>'x','channel'=>'sms','content'=>'x'];$this->postJson('/api/notifications/batch',['notifications'=>$items])->assertUnprocessable();}
 public function test_health_endpoint():void{$this->getJson('/api/health')->assertOk()->assertJsonPath('status','UP');}
}
