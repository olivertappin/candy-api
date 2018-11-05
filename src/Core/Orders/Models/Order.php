<?php

namespace GetCandy\Api\Core\Orders\Models;

use GetCandy\Api\Core\Auth\Models\User;
use GetCandy\Api\Core\Scaffold\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use GetCandy\Api\Core\Baskets\Models\Basket;
use GetCandy\Api\Core\Payments\Models\Transaction;

class Order extends BaseModel
{
    protected $hashids = 'order';

    protected $fillable = [
        'lines',
        'delivery_total',
        'tax_total',
        'discount_total',
        'sub_total',
        'order_total',
        'status',
        'shipping_preference',
        'shipping_method',
        'billing_phone',
        'billing_firstname',
        'billing_lastname',
        'billing_address',
        'billing_address_two',
        'billing_address_three',
        'billing_city',
        'billing_county',
        'billing_state',
        'billing_country',
        'billing_zip',
        'billing_phone',
        'shipping_firstname',
        'shipping_lastname',
        'shipping_address',
        'shipping_address_two',
        'shipping_address_three',
        'shipping_city',
        'shipping_county',
        'shipping_state',
        'shipping_country',
        'shipping_zip',
    ];

    protected $dates = [
        'placed_at',
    ];

    protected $required = [
        'currency',
        'billing_firstname',
        'billing_lastname',
        'billing_address',
        'billing_city',
        'billing_country',
        'billing_zip',
    ];

    public function getRequiredAttribute()
    {
        return collect($this->required);
    }

    public function getDisplayIdAttribute()
    {
        return '#ORD-'.str_pad($this->id, 4, 0, STR_PAD_LEFT);
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('open', function (Builder $builder) {
            $builder->whereNull('placed_at');
        });

        static::addGlobalScope('not_expired', function (Builder $builder) {
            $builder->where('status', '!=', 'expired');
        });
    }

    public function scopeSearch($qb, $keywords)
    {
        // Do some stuff.
        // Explode by commas
        $segments = explode(',', $keywords);

        $matches = [];

        foreach ($segments as $segment) {
            $segments = explode(' ', $segment);
            array_push($matches, $segments);
        }

        $matches = array_flatten($matches);

        if (count($matches) > 1) {
            $query = $qb->where('billing_firstname', 'LIKE', '%'.$matches[0].'%')
                ->where('billing_lastname', 'LIKE', '%'.$matches[1].'%');
        } else {
            $query = $qb->whereIn('billing_firstname', $matches)
            ->orWhereIn('billing_lastname', $matches);
        }

        // Need to be able to search on order total
        foreach ($matches as $match) {
            if (is_numeric($match)) {
                $query->orWhere('order_total', '=', $match * 100);
            }
        }

        $query->orWhereIn('id', $matches)
            ->orWhereIn('contact_email', $matches)
            ->orWhereIn('reference', $matches);

        return $query;
    }

    // /**
    //  * Gets the order total with tax.
    //  *
    //  * @return mixed
    //  */
    // public function getTotalAttribute()
    // {
    //     return $this->lines->sum(function ($line) {
    //         return ($line->line_amount - $line->discount) + $line->tax;
    //     });
    // }

    // public function getShippingTotalAttribute()
    // {
    //     return $this->lines->where('shipping', true)->sum(function ($line) {
    //         return $line->line_amount - $line->discount;
    //     });
    // }

    // public function getDiscountAttribute()
    // {
    //     return $this->lines->sum('discount');
    // }

    // public function getTaxAttribute()
    // {
    //     return $this->lines->sum('tax');
    // }

    // public function getShippingAttribute()
    // {
    //     return $this->lines->where('shipping', '=', 1)->first();
    // }

    /**
     * Gets the shipping details.
     *
     * @return array
     */
    public function getShippingDetailsAttribute()
    {
        return $this->getDetails('shipping');
    }

    /**
     * Gets back the billing details.
     *
     * @return array
     */
    public function getBillingDetailsAttribute()
    {
        return $this->getDetails('billing');
    }

    public function getTotalAttribute()
    {
        return $this->sub_total + $this->delivery_total + $this->tax_total;
    }

    /**
     * Gets the details, mainly for contact info.
     *
     * @param string $type
     *
     * @return array
     */
    protected function getDetails($type)
    {
        return collect($this->attributes)->filter(function ($value, $key) use ($type) {
            return strpos($key, $type.'_') === 0;
        })->mapWithKeys(function ($item, $key) use ($type) {
            $newkey = str_replace($type.'_', '', $key);

            return [$newkey => $item];
        })->toArray();
    }

    public function getInvoiceReferenceAttribute()
    {
        if ($this->reference) {
            return '#INV-'.str_pad($this->reference, 4, 0, STR_PAD_LEFT);
        }
    }

    public function getCustomerNameAttribute()
    {
        $name = null;

        if ($billing = $this->getDetails('billing')) {
            return $billing['firstname'].' '.$billing['lastname'];
        }

        if ($this->user) {
            if ($this->user->company_name) {
                $name = $this->user->company_name;
            } elseif ($this->user->name) {
                $name = $this->user->name;
            }
        }

        if (! $name || $name == ' ') {
            return 'Guest Checkout';
        }

        return $name;
    }

    /**
     * Get the basket lines.
     *
     * @return void
     */
    public function lines()
    {
        return $this->hasMany(OrderLine::class)->orderBy('is_shipping', 'asc');
    }

    /**
     * Gets all order lines that are from the basket.
     *
     * @return void
     */
    public function basketLines()
    {
        return $this->hasMany(OrderLine::class)->whereIsShipping(false)->whereIsManual(false);
    }

    public function basket()
    {
        return $this->belongsTo(Basket::class);
    }

    /**
     * Get the basket user.
     *
     * @return User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function discounts()
    {
        // dd($this->id);
        return $this->hasMany(OrderDiscount::class);
    }
}
