<?php
namespace App\Enums;
enum NotificationPriority: string { case HIGH='high'; case NORMAL='normal'; case LOW='low'; public function queue(): string { return $this->value; } }
