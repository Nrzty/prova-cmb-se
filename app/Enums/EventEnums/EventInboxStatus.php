<?php

namespace App\Enums\EventEnums;
enum EventInboxStatus: string
{
    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
}
