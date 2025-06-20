<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

trait HandlesFiles
{




    public function uploadFile($file, $fieldName = 'profile_picture')
    {
        try {
            // Get config - handle special case for users
            $config = $this->fileHandlingConfig[$fieldName] ?? [
                'directory' => 'user_profile_pictures',
                'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
                'max_size' => 3072,
                'default' => 'default-user.jpg'
            ];

            // Validate the file
            if (!$file->isValid()) {
                throw new \Exception("Invalid file upload");
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $config['allowed_types'])) {
                throw new \Exception("File type not allowed. Allowed types: " . implode(',', $config['allowed_types']));
            }

            if ($file->getSize() > ($config['max_size'] * 1024)) {
                throw new \Exception("File size exceeds maximum allowed size of {$config['max_size']}KB");
            }

            // Generate secure filename
            $filename = Str::uuid()->toString() . '.' . $extension;
            $directory = trim($config['directory'], '/');

            // Store the file and get the path
            $path = $file->storeAs($directory, $filename, 'public');

            // Save the relative path (without 'public/' prefix)
            $this->{$fieldName} = $path;
            $this->save();

            return $path;
        } catch (\Exception $e) {
            Log::error("File upload failed: " . $e->getMessage(), [
                'model' => get_class($this),
                'id' => $this->id,
                'field' => $fieldName,
                'error' => $e->getTraceAsString()
            ]);
            return false;
        }
    }





    /**
     * Gets the URL for a file field
     */


    public function getFileUrl($fieldName = 'profile_picture')
    {
        if (!isset($this->fileHandlingConfig[$fieldName])) {
            return null;
        }

        $config = $this->fileHandlingConfig[$fieldName] ?? [
            'directory' => 'user_profile_pictures',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
            'max_size' => 3072,
        ];
        // If no file is set, return default
        if (empty($this->{$fieldName})) {
            return asset('storage/' . $config['directory'] . '/' . $config['default']);
        }

        // Get the stored path
        $path = $this->{$fieldName};

        // Ensure the path doesn't already contain 'storage/' or 'public/'
        $path = ltrim(str_replace(['storage/', 'public/'], '', $path), '/');

        // Check if file exists in storage
        if (Storage::disk('public')->exists($path)) {
            return asset('storage/' . $path);
        }

        // Return default if file not found
        return asset('storage/' . $config['directory'] . '/' . $config['default']);
    }




    /**
     * Deletes a file
     */
    public function deleteFile($fieldName = 'profile_picture')
    {
        try {
            if ($this->{$fieldName} && Storage::disk('public')->exists($this->{$fieldName})) {
                Storage::disk('public')->delete($this->{$fieldName});
                $this->{$fieldName} = null;
                $this->save();

                Log::info("File deleted successfully for {$fieldName}", [
                    'model' => get_class($this),
                    'id' => $this->id
                ]);
            }
            return true;
        } catch (\Exception $e) {
            Log::error("File deletion failed: " . $e->getMessage(), [
                'model' => get_class($this),
                'id' => $this->id,
                'field' => $fieldName
            ]);
            return false;
        }
    }


    /**
     * Specific method for profile picture URL (for backward compatibility)
     */
    public function getProfilePictureUrl()
    {
        return $this->getFileUrl('profile_picture');
    }
}
