<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EventInbox extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    protected $fillable = [
        'idempotency_key',
        'source',
        'type',
        'payload',
        'status',
        'error'
    ];
}
