<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'role', 'password',
        'class_level_id', 'phone', 'dob', 'school', 'city', 'state', 'photo', 'is_active',
        'referral_code', 'referred_by',
    ];

    protected $casts_extra = [];

    /**
     * Mint a unique referral code on creation. This is the single place codes
     * are generated.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->referral_code)) {
                do {
                    $code = Str::upper(Str::random(8));
                } while (static::where('referral_code', $code)->exists());

                $user->referral_code = $code;
            }
        });
    }

    /**
     * Attributes appended to the model's array / JSON form.
     *
     * @var list<string>
     */
    protected $appends = ['photo_url'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Public URL for the student's profile photo (null if none uploaded).
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo) : null;
    }

    public function classLevel()
    {
        return $this->belongsTo(\App\Models\ClassLevel::class);
    }

    public function examAttempts()
    {
        return $this->hasMany(\App\Models\ExamAttempt::class);
    }

    public function studentNotifications()
    {
        return $this->hasMany(\App\Models\StudentNotification::class);
    }

    public function enrollments()
    {
        return $this->hasMany(\App\Models\ExamEnrollment::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }

    /** Referrals where this user is the one who shared the link. */
    public function referralsMade()
    {
        return $this->hasMany(\App\Models\Referral::class, 'referrer_id');
    }

    /** The single referral row where this user is the referee (if referred). */
    public function referralRecord()
    {
        return $this->hasOne(\App\Models\Referral::class, 'referee_id');
    }

    /** The user who referred this student, if any. */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /** Public sign-up link carrying this student's referral code. */
    public function referralLink(): string
    {
        return route('register', ['ref' => $this->referral_code]);
    }

    /** Count of this student's referrals that have qualified (or been rewarded). */
    public function qualifiedReferralsCount(): int
    {
        return $this->referralsMade()
            ->whereIn('status', ['qualified', 'rewarded'])
            ->count();
    }

    /** Live, unredeemed personal reward coupons this student can spend at checkout. */
    public function availableRewards()
    {
        return Coupon::where('owner_user_id', $this->id)
            ->where('source', 'referral_reward')
            ->where('is_active', true)
            ->where('used_count', 0)
            ->get();
    }

    /**
     * Whether this student already holds an active enrolment for the given exam.
     */
    public function isEnrolledIn(int $examId): bool
    {
        return $this->enrollments()
            ->where('exam_id', $examId)
            ->where('status', 'enrolled')
            ->exists();
    }

    public static function indianStates(): array
    {
        return [
            'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
            'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
            'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
            'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
            'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
            'Delhi', 'Jammu & Kashmir', 'Ladakh', 'Chandigarh', 'Puducherry',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dob' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
