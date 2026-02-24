<?php

namespace Hemant\Pagewire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageContent extends Model
{
    protected $fillable = [
        'page_id',
        'global_section_id',
        'is_global_override',
        'section_name',
        'col_span',
        'content',
        'sort_order',
    ];

    protected $casts = [
        'content' => 'array',
        'is_global_override' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function globalSection(): BelongsTo
    {
        return $this->belongsTo(GlobalSection::class);
    }
}
