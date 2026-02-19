<?php

namespace Hemant\Pagewire\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'meta_description',
        'meta_keywords',
        'is_published',
        'published_at',
        'admin_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function contents(): HasMany
    {
        return $this->hasMany(PageContent::class)->orderBy('sort_order');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(config('pagewire.user_model'), 'admin_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
