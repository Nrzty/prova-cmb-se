<?php

namespace App\Support\Database;

use App\Enums\SqlEnums\SqlUniqueViolation;
use Illuminate\Database\QueryException;

class DatabaseErrorHelper
{
    public static function isUniqueViolation(QueryException $exception): bool
    {
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($errorCode, array_column(SqlUniqueViolation::cases(), 'value'));
    }
}

