<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Certificate extends Model
{
    protected $fillable = [
        'exam_id', 'user_id', 'type', 'file_path', 'original_name',
        'mime_type', 'file_size', 'is_active', 'uploaded_by', 'generated_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'generated_at' => 'datetime',
        'file_size'    => 'integer',
    ];

    protected $appends = ['download_url'];

    public function exam()       { return $this->belongsTo(Exam::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function uploadedBy() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getDownloadUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }
}
