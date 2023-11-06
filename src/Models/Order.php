<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Order extends Model
{
    use Mediable, LogsActivity;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'model_id',
        'model_type',
        'order_type',
        'order_total',
        'order_status',
        'transaction_id',
        'order_uuid',
        'cart_data',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'date:m/d/Y',
        'updated_at' => 'date:m/d/Y',
        'cart_data' => 'array',
    ];

    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getAddressAttribute()
    {
        return $this->address_1 . ' ' . $this->address_2 . '<br />' . $this->city . ', ' . $this->region . ' ' . $this->postal_code . ', ' . $this->country;
    }

    public function getInlineAddressAttribute()
    {
        $address = $this->address_1;

        if ($this->address_2) $address .= ' ' . $this->address_2;

        $address .= ' ';

        if ($this->city) $address .= $this->city . ', ';
        if ($this->region) $address .= ' ' . $this->region;
        if ($this->postal_code) $address .= ' ' . $this->postal_code;

        return $address;
    }

    public function getHtmlAddressAttribute()
    {
        $address = $this->address_1;

        if ($this->address_2) $address .= ' ' . $this->address_2;

        $address .= '<br />';

        if ($this->city) $address .= $this->city . ', ';
        if ($this->region) $address .= ' ' . $this->region;
        if ($this->postal_code) $address .= ' ' . $this->postal_code;

        return $address;
    }

    public function model()
    {
        return $this->morphTo();
    }

    public function getOrderFileAttribute()
    {
        $media_url = NULL;
        if ($this->firstMedia('order_file')) {
            $media_url = (new \App\Services\MediaService)->get_signed_url($this->firstMedia('order_file'));
        }

        return $media_url;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(array_diff($this->fillable, $this->hidden))
            ->logOnlyDirty()
            ->useLogName('system')
            ->dontSubmitEmptyLogs();
    }
}
