<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Invoice extends Model
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
        'subscription_id',
        'payment_id',
        'amount',
        'invoice_date',
    ];

    public function payment()
    {
        return $this->belongsTo('App\Models\Payment');
    }

    public function subscription()
    {
        return $this->belongsTo('App\Models\Subscription');
    }

    public function model()
    {
        return $this->morphTo();
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
