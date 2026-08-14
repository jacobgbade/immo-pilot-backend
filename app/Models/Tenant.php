<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'app_user_id',
        'name',
        'phone',
        'email',
    ];

    /** The landlord who owns this tenant record. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The tenant's own login account, if they've rattaché their app account. */
    public function appUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'app_user_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }
}
