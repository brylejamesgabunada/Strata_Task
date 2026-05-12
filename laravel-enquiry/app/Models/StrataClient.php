<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrataClient extends Model
{
    protected $fillable = [
        'client_id',
        'full_name',
        'email',
        'phone',
        'status',
        'account_manager',
        'since',
        'portal_access',
        'open_requests',
        'levy_status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'portal_access' => 'boolean',
            'since' => 'date',
        ];
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ClientLot::class);
    }
}
