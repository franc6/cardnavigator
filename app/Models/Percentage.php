<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents the cashback or reward percentage for a card and category pair.
 *
 * @property int $id
 * @property int $card_id
 * @property string $category
 * @property int $percentage
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Card $card
 *
 * @method static \Database\Factories\PercentageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Percentage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Percentage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Percentage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Percentage whereCardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Percentage whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Percentage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Percentage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Percentage wherePercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Percentage whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Percentage extends Model
{
    use HasFactory;

    /**
     * Attributes that are mass-assignable when creating or updating a Percentage.
     *
     * @var list<string>
     */
    protected $fillable = ['card_id', 'category', 'percentage'];

    /**
     * Belongs-to relationship to the owning card.
     *
     * @return BelongsTo The Card that owns this percentage record.
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
