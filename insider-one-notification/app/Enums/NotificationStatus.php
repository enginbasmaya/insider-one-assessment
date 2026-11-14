<?php
namespace App\Enums;
enum NotificationStatus: string { case PENDING='pending'; case PROCESSING='processing'; case ACCEPTED='accepted'; case FAILED='failed'; case CANCELLED='cancelled'; }
