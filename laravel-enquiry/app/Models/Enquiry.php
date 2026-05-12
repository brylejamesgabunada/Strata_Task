<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $fillable = [
        'public_id',
        'client_email',
        'client_name',
        'building_name',
        'building_size',
        'message',
        'status',
        'n8n_response',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'n8n_response' => 'array',
        ];
    }
}
