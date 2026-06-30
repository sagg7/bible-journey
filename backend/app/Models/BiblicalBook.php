<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiblicalBook extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public function translations(): HasMany
    {
        return $this->hasMany(BookTranslation::class);
    }

    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(BibleChapter::class);
    }

    public function readingBlocksAsStart(): HasMany
    {
        return $this->hasMany(ReadingBlock::class, 'start_book_id');
    }
}
