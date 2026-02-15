<?php

namespace App\Enums\EventEnums;
enum EventInboxType: string
{
    case CREATED = 'occurrence.created';
    case UPDATED = 'occurrence.updated';
    case CLOSED = 'occurrence.closed';
}
