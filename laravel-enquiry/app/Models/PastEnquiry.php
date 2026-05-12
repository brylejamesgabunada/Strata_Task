<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PastEnquiry extends Model
{
    protected $fillable = [
        'inquiry_id',
        'category',
        'subcategory',
        'urgency',
        'client_status',
        'summary',
        'original_message',
        'recommended_action',
        'suggested_response',
        'previous_resolution',
        'page_content',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
