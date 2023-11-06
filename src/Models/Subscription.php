<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Subscription extends Model
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
        'name',
        'subscription_status',
        'subscription_type',
        'subscription_tier',
        'starts_at',
        'ends_at',
        'amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'starts_at' => 'date:m/d/Y',
        'ends_at' => 'date:m/d/Y',
    ];

    public function model()
    {
        return $this->morphTo();
    }

    public function invoices()
    {
        return $this->hasMany('App\Models\Invoice');
    }

    public function unpaid_invoices()
    {
        return $this->hasMany('App\Models\Invoice')->whereNull('payment_id');
    }

    public function last_unpaid_invoice()
    {
        return $this->hasMany('App\Models\Invoice')->whereNull('payment_id')->orderBy('invoice_date', 'DESC')->first();
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
