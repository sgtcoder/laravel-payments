<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PaymentProfile extends Model
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
        'payment_gateway',
        'payment_method_id',
        'last_4',
        'exp_date',
        'card_type',
        'cardholder_name',
    ];

    public function model()
    {
        return $this->morphTo();
    }

    public function getPaymentTitleAttribute()
    {
        return strtoupper($this->card_type) . ', Ending in ' . $this->last_4 . ', Expires ' . $this->exp_date;
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
