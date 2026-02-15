<?php

namespace App\Enums\EventInboxEnums;
enum EventInboxSource: string
{
    case EXTERNAL_SYSTEM = 'sistema_externo';
    case WEB_OPERATOR = 'operador_web';
}
