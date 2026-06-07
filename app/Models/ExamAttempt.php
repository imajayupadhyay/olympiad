<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    protected $fillable = [
        'user_id', 'exam_id', 'status',
        'started_at', 'submitted_at', 'time_taken_seconds',
        'total_score', 'total_attempted', 'total_correct', 'total_wrong', 'total_skipped',
        'ip_address',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
        'total_score'  => 'float',
    ];

    public function user()   { return $this->belongsTo(User::class); }
    public function exam()   { return $this->belongsTo(Exam::class); }
    public function answers(){ return $this->hasMany(Answer::class); }
    public function result() { return $this->hasOne(Result::class); }
}
