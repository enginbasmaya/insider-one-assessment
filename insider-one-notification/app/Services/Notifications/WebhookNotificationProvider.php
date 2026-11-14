<?php
namespace App\Services\Notifications;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;
class WebhookNotificationProvider implements NotificationProvider {
 public function send(Notification $notification): ProviderResponse {
  $url=config('services.webhook.url'); if(!$url) return new ProviderResponse(false,null,'WEBHOOK_PROVIDER_URL is not configured');
  try {
   $response=Http::timeout(config('services.webhook.timeout',10))->acceptJson()->post($url,['to'=>$notification->recipient,'channel'=>$notification->channel->value,'content'=>$notification->content,'messageId'=>$notification->id,'correlationId'=>$notification->correlation_id]);
   if($response->status()!==202) return new ProviderResponse(false,null,'Provider returned HTTP '.$response->status().': '.substr($response->body(),0,500));
   $body=$response->json(); return new ProviderResponse(true,$body['messageId']??(string)Str::uuid());
  } catch(Throwable $e) { return new ProviderResponse(false,null,$e->getMessage()); }
 }
}
