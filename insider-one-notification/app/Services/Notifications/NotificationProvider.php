<?php
namespace App\Services\Notifications;
use App\Models\Notification;
interface NotificationProvider { public function send(Notification $notification): ProviderResponse; }
