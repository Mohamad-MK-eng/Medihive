<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait HandlesFiles
{
    /**
     * Uploads a file with configurable options
     */
    public function uploadFile($file, $fieldName = 'profile_picture', $directory = 'profile_pictures', $allowedTypes = ['jpg', 'jpeg', 'png'], $maxSize = 2048000)
    {
        // Validate the file
        if (!$file->isValid() ||
            !in_array(strtolower($file->getClientOriginalExtension()), $allowedTypes) ||
            $file->getSize() > $maxSize) {
            return false;
        }

        // Generate unique filename
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $path = $directory.'/'.$filename;

        // Store the file
        Storage::disk('public')->put($path, file_get_contents($file));

        // Delete old file if exists
        if ($this->{$fieldName}) {
            $this->deleteFile($fieldName);
        }

        // Save new path
        $this->{$fieldName} = $path;
        $this->save();

        return $path;
    }

    /**
     * Gets the URL for a file field
     */
    public function getFileUrl($fieldName = 'profile_picture', $default = 'images/default-profile.jpg')
    {
        return $this->{$fieldName}
            ? Storage::url($this->{$fieldName})
            : asset($default);
    }

    /**
     * Deletes a file
     */
    public function deleteFile($fieldName = 'profile_picture')
    {
        if ($this->{$fieldName} && Storage::disk('public')->exists($this->{$fieldName})) {
            Storage::disk('public')->delete($this->{$fieldName});
            $this->{$fieldName} = null;
            $this->save();
        }
        return true;
    }
}
