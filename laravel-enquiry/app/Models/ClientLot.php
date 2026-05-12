<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLot extends Model
{
    protected $fillable = [
        'strata_client_id',
        'lot_number',
        'building',
        'plan_number',
        'role',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(StrataClient::class, 'strata_client_id');
    }
}
