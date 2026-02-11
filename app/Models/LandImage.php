<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandImage extends Model
{
    use HasFactory;

    protected $fillable = ['land_id', 'image_path'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        // If it's already a full URL (http:// or https://), return as-is
        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }
        
        // Otherwise, use storage path for local files
        return asset('storage/' . $this->image_path);
    }
    
    public function land()
    {
        return $this->belongsTo(Land::class);
    }
}
