<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'name',
        'description',
        'max_members',
        'qr_code_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            if (empty($group->qr_code_token)) {
                do {
                    $token = Str::random(32);
                } while (static::where('qr_code_token', $token)->exists());
                $group->qr_code_token = $token;
            }
        });
    }

    public function regenerateQrToken(): void
    {
        do {
            $token = Str::random(32);
        } while (static::where('qr_code_token', $token)->exists());

        $this->update(['qr_code_token' => $token]);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }
}
