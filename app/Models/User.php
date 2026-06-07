<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
    ];

    protected $casts_extra = [];

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
