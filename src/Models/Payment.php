<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payment extends Model
{
    use LogsActivity;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'model_id',
        'model_type',
        'email',
        'subscription_id',
        'invoice_id',
        'payment_profile_id',
        'payment_type',
        'transaction_id',
        'transaction_tag',
        'amount',
        'currency',
        'merchant_ref',
        'payment_status',
        'error_message',
        'payment_batch_id',
        'payment_profile_id',
        'last_4',
        'exp_date',
        'card_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'date:m/d/Y',
        'updated_at' => 'date:m/d/Y',
    ];

    public function model()
    {
        return $this->morphTo();
    }

    public function payment_profile()
    {
        return $this->belongsTo('App\Models\PaymentProfile');
    }

    public function subscription()
    {
        return $this->belongsTo('App\Models\Subscription');
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
