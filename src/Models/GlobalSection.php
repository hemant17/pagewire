<?php

namespace Hemant\Pagewire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalSection extends Model
{
    protected $fillable = [
        'name',
        'section_name',
        'content',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function contents(): HasMany
    {
        return $this->hasMany(PageContent::class);
    }
}
