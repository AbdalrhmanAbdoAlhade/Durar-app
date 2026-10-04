<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleImage extends Model
{
    protected $fillable = ['article_id', 'image', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}