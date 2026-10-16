<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'order_number',
    'status',
    'payment_method',
    'payment_phone',
    'subtotal',
    'total',
    'notes',
])]
class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_COD = 'cash_on_delivery';

    public const PAYMENT_SYRIATEL_CASH = 'syriatel_cash';

    /** Historical only — not selectable for new orders. */
    public const PAYMENT_SHAM_CASH = 'sham_cash';

    /** Historical only — not selectable for new orders. */
    public const PAYMENT_DEMO = 'demo';

    public const SYRIATEL_PHONE_PATTERN = '/^09\d{8}$/';

    /**
     * @return list<string>
     */
    public static function selectablePaymentMethods(): array
    {
        return [
            self::PAYMENT_COD,
            self::PAYMENT_SYRIATEL_CASH,
        ];
    }
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
