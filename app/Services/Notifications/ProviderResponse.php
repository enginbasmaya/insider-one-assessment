<?php
namespace App\Services\Notifications;
class ProviderResponse { public function __construct(public readonly bool $accepted, public readonly ?string $messageId=null, public readonly ?string $error=null) {} }
