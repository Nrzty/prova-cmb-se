<?php

namespace App\Enums\DispatchEnums;

enum DispatchStatus: string
{
    case ASSIGNED = 'assigned';
    case EN_ROUTE = 'en_route';
    case ON_SITE = 'on_site';
    case CLOSED = 'closed';
}

