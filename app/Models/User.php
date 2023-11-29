<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'slack_user_id',
        'slack_channel_id',
    ];

    protected $casts = [
        'send_invoice_at' => 'datetime',
    ];

    public function getGoogleAccessTokenAttribute($value)
    {
        return json_decode(Crypt::decrypt($value), true);
    }

    public function setGoogleAccessTokenAttribute($value)
    {
        $this->attributes['google_access_token'] = Crypt::encrypt(json_encode($value));
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slack_user_id';
    }
}
