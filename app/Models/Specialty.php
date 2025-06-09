<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HandlesFiles;
use Storage;

class Specialty extends Model
{
    use HandlesFiles;

    protected $fillable = [
        'name',
        'description',
        'image_path'
    ];

    public function getIconUrl()
    {
        if (!$this->image_path) {
            return asset('images/default-specialty.png');
        }

        // Manually construct the URL to ensure consistency
        return url('storage/specialty_icons/' . basename($this->image_path));
    }

    public function uploadIcon($iconFile)
    {
        if ($iconFile) {
            // Delete old icon if exists
            if ($this->image_path) {
                Storage::disk('public')->delete($this->image_path);
            }

            // Store in specialty_icons directory
            $path = $iconFile->store('specialty_icons', 'public');
            $this->image_path = $path;
            return $this->save();
        }
        return false;
    }
}
