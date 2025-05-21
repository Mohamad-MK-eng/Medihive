<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait HandlesFiles
{
    public function uploadProfilePicture($file)
    {
        // Validate it's a JPG image (max 2MB)
        if (!$file->isValid() ||
            !in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg']) ||
            $file->getSize() > 2000000) {
            return false;
        }

        // Generate unique filename
        $filename = 'profile_'.time().'.'.$file->getClientOriginalExtension();
        $path = 'profile_pictures/'.$filename;

        // Store the file
        Storage::disk('public')->put($path, file_get_contents($file));

        // Delete old picture if exists
        if ($this->profile_picture) {
            Storage::disk('public')->delete($this->profile_picture);
        }

        // Save new path
        $this->profile_picture = $path;
        $this->save();

        return $path;
    }

    public function getProfilePictureUrl()
    {
        return $this->profile_picture
            ? Storage::disk('public')->url($this->profile_picture)
            : asset('images/default-profile.jpg');
    }
}
