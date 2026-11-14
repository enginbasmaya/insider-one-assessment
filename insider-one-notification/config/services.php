<?php
return ['webhook'=>['url'=>env('WEBHOOK_PROVIDER_URL'),'timeout'=>(int)env('WEBHOOK_TIMEOUT',10),'retries'=>(int)env('WEBHOOK_RETRIES',3)]];
