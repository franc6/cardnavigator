<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a credit or rewards card.
 *
 * @property int $id
 * @property string $name
 * @property int $foreign_transaction_fee
 * @property int $preference
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $image_data
 * @property string|null $image_mime
 * @property string|null $color
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Percentage> $percentages
 * @property-read int|null $percentages_count
 *
 * @method static \Database\Factories\CardFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card whereForeignTransactionFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card whereImageData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card whereImageMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card wherePreference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Card whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Card extends Model
{
    use HasFactory;

    /**
     * Attributes that are mass-assignable when creating or updating a Card.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'foreign_transaction_fee', 'preference', 'image_data', 'image_mime', 'color'];

    /**
     * One-to-many relationship to category cashback percentages.
     *
     * @return HasMany All Percentage records belonging to this card.
     */
    public function percentages(): HasMany
    {
        return $this->hasMany(Percentage::class);
    }
}
