<?php

namespace Modules\HostetskiGPT\Entities;

use Illuminate\Database\Eloquent\Model;

class ConversationSummary extends Model
{
    protected $table = 'conversation_summaries';
    
    protected $fillable = [
        'conversation_id',
        'summary',
        'last_updated'
    ];

    protected $dates = [
        'last_updated',
        'created_at',
        'updated_at'
    ];
} 