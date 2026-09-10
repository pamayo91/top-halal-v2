<?php

namespace App\Notifications;

trait TransactionalMailQueueSettings
{
    public int $tries = 4;
    public int $timeout = 75;
    public array $backoff = [30, 120, 300];
}
