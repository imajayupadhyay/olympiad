<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row referral program configuration. Always read via current().
 */
class ReferralSetting extends Model
{
    protected $fillable = [
        'is_active',
        'referee_discount_type',
        'referee_discount_value',
        'referee_max_discount',
        'referee_min_order_amount',
        'referrer_reward_type',
        'referrer_reward_value',
        'referrer_max_discount',
        'unlock_threshold',
        'qualify_on',
        'reward_validity_days',
    ];

    protected $casts = [
        'is_active'                => 'boolean',
        'referee_discount_value'   => 'decimal:2',
        'referee_max_discount'     => 'decimal:2',
        'referee_min_order_amount' => 'decimal:2',
        'referrer_reward_value'    => 'decimal:2',
        'referrer_max_discount'    => 'decimal:2',
        'unlock_threshold'         => 'integer',
        'reward_validity_days'     => 'integer',
    ];

    /** Human-readable referee welcome discount, e.g. "20% off (up to ₹100)". */
    public function refereeDiscountLabel(): string
    {
        return self::discountLabel(
            $this->referee_discount_type,
            (float) $this->referee_discount_value,
            $this->referee_max_discount !== null ? (float) $this->referee_max_discount : null,
        );
    }

    /** Human-readable referrer reward, e.g. "₹50 off". */
    public function referrerRewardLabel(): string
    {
        return self::discountLabel(
            $this->referrer_reward_type,
            (float) $this->referrer_reward_value,
            $this->referrer_max_discount !== null ? (float) $this->referrer_max_discount : null,
        );
    }

    public static function discountLabel(string $type, float $value, ?float $cap): string
    {
        if ($value <= 0) {
            return '';
        }

        if ($type === 'percentage') {
            $label = rtrim(rtrim(number_format($value, 2), '0'), '.').'% off';

            return $cap ? $label.' (up to ₹'.number_format($cap, 0).')' : $label;
        }

        return '₹'.number_format($value, 0).' off';
    }

    /** The single config row, created with safe defaults on first access. */
    public static function current(): self
    {
        // Singleton: reuse the earliest row, else create one. (`id` is non-fillable,
        // so we can't pin to id=1 via firstOrCreate.)
        return static::orderBy('id')->first() ?? static::create([
            'is_active'                => false,
            'referee_discount_type'    => 'percentage',
            'referee_discount_value'   => 0,
            'referee_min_order_amount' => 0,
            'referrer_reward_type'     => 'percentage',
            'referrer_reward_value'    => 0,
            'unlock_threshold'         => 1,
            'qualify_on'               => 'registration',
        ]);
    }
}
