<?php

namespace App\Enums\EventInboxEnums;

enum EventInboxType: string
{
    case CREATED = 'occurrence.created';
    case UPDATED = 'occurrence.updated';
    case CLOSED = 'occurrence.closed';
    case STARTED = 'occurrence.started';
    case RESOLVED = 'occurrence.resolved';
    case DISPATCH_CREATED = 'dispatch.created';
    case DISPATCH_CLOSED = 'dispatch.closed';
}
