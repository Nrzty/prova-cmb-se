<?php

namespace App\Enums\EventInboxEnums;
enum EventInboxStatus: string
{
    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
}
