<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maps a Google Places API type to a user-friendly category name.
 *
 * @property int $id
 * @property string $name
 * @property string|null $friendly_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Percentage> $percentages
 * @property-read int|null $percentages_count
 *
 * @method static \Database\Factories\CategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereFriendlyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Category extends Model
{
    use HasFactory;

    /**
     * Attributes that are mass-assignable when creating or updating a Category.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'friendly_name'];

    /**
     * One-to-many relationship to percentages via the friendly_name → category key.
     *
     * @return HasMany All Percentage records whose category column matches this category's friendly_name.
     */
    public function percentages(): HasMany
    {
        return $this->hasMany(Percentage::class, 'category', 'friendly_name');
    }
}
