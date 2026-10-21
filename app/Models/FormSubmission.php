<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $fillable = ['form', 'name', 'email', 'phone', 'data', 'attachment_path', 'attachment_name', 'source_url', 'ip', 'user_agent'];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];
}
