<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\PhoneNumberService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
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
        'name', 'email', 'role', 'registration_source', 'password', 'password_changed_at',
        'class_level_id', 'phone', 'phone_e164', 'phone_verified_at', 'dob', 'school', 'school_address', 'city', 'pincode', 'state', 'photo', 'is_active',
        'referral_code', 'referred_by',
    ];

    /** Where a student account was created — drives campaign reporting in the admin panel. */
    public const REGISTRATION_SOURCES = [
        'website' => 'Website',
        'marketing' => 'Marketing page',
        'admin' => 'Added by admin',
    ];

    public function registrationSourceLabel(): string
    {
        return self::REGISTRATION_SOURCES[$this->registration_source] ?? 'Website';
    }

    protected $casts_extra = [];

    /**
     * Mint a unique referral code on creation. This is the single place codes
     * are generated.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('email')) {
                $user->email = Str::lower(trim((string) $user->email));

                if ($user->exists) {
                    $user->email_verified_at = null;
                }
            }

            if ($user->isDirty('phone')) {
                $normalized = app(PhoneNumberService::class)
                    ->tryNormalize($user->phone);

                if ($normalized) {
                    if ($user->exists && $user->getOriginal('phone_e164') !== $normalized) {
                        $user->phone_verified_at = null;
                    }

                    $user->phone = $normalized;
                    $user->phone_e164 = $normalized;
                } elseif (! filled($user->phone)) {
                    $user->phone = null;
                    $user->phone_e164 = null;
                    $user->phone_verified_at = null;
                }
            }
        });

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
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    public function classLevel()
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function studentNotifications()
    {
        return $this->hasMany(StudentNotification::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function enrollments()
    {
        return $this->hasMany(ExamEnrollment::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function loginOtpChallenges()
    {
        return $this->hasMany(LoginOtpChallenge::class, 'selected_user_id');
    }

    /** Referrals where this user is the one who shared the link. */
    public function referralsMade()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /** The single referral row where this user is the referee (if referred). */
    public function referralRecord()
    {
        return $this->hasOne(Referral::class, 'referee_id');
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
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'dob' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * True while the student is still on the auto-generated password that was
     * emailed at registration (they have not set one of their own yet).
     */
    public function usingGeneratedPassword(): bool
    {
        return is_null($this->password_changed_at);
    }

    /**
     * Profile-completion breakdown used to drive the engagement progress bar.
     * Core fields (name/email/class) are always present from registration; the
     * rest are what the student fills in later on their profile page.
     *
     * @return array{percent:int, filled:int, total:int, missing:list<string>}
     */
    public function profileCompletion(): array
    {
        $fields = [
            'Full name' => filled($this->name),
            'Email' => filled($this->email),
            'Class' => filled($this->class_level_id),
            'Phone' => filled($this->phone),
            'Date of birth' => filled($this->dob),
            'School' => filled($this->school),
            'School address' => filled($this->school_address),
            'City' => filled($this->city),
            'PIN code' => filled($this->pincode),
            'State' => filled($this->state),
            'Photo' => filled($this->photo),
        ];

        $total = count($fields);
        $filled = count(array_filter($fields));

        return [
            'percent' => (int) round($filled / $total * 100),
            'filled' => $filled,
            'total' => $total,
            'missing' => array_keys(array_filter($fields, fn ($ok) => ! $ok)),
        ];
    }
}
