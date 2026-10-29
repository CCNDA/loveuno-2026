<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'body', 'published_at'])]
class Announcement extends Model
{
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function scopePublished(Builder $q): void
    {
        $q->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }
}
