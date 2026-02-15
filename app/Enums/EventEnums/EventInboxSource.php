<?php

namespace App\Enums\EventEnums;
enum EventInboxSource: string
{
    case EXTERNAL_SYSTEM = 'sistema_externo';
    case WEB_OPERATOR = 'operador_web';
}
