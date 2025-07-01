<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ExternalApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'expires_at',
        'usage_count',
        'last_used_at',
    ];

    protected $dates = [
        'expires_at',
        'last_used_at',
    ];

    public $timestamps = true;

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }
}
