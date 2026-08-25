<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $guarded = [];

    protected $hidden = ['author_email'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function article(): BelongsTo { return $this->belongsTo(Article::class); }
    public function page(): BelongsTo { return $this->belongsTo(Page::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
}
