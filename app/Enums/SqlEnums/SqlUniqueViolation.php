<?php

namespace App\Enums\SqlEnums;

enum SqlUniqueViolation : int
{
    case PGSQL_UNIQUE_VIOLATION = 23505;
    case SQLITE_UNIQUE_VIOLATION = 19;
}
