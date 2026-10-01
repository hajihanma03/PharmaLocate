<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pharmacy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'contact_number',
        'operating_hours',
        'latitude',
        'longitude',
        'is_active',
        'inside_tph',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'inside_tph' => 'boolean',
    ];

    /** Community pharmacies around TPH — not stores operating inside the hospital. */
    public function scopePublicLocator($query)
    {
        return $query->where('is_active', true)->where('inside_tph', false);
    }

    /**
     * Medicines stocked by this pharmacy, with inventory pivot data
     * (stock_quantity, price, availability_status).
     */
    public function medicines(): BelongsToMany
    {
        return $this->belongsToMany(Medicine::class, 'pharmacy_medicine')
            ->withPivot(['stock_quantity', 'price', 'availability_status'])
            ->withTimestamps();
    }

    public function geofences(): BelongsToMany
    {
        return $this->belongsToMany(Geofence::class)->withTimestamps();
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
