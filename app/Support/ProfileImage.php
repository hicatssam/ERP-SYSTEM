<?php

namespace App\Support;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Storage;

final class ProfileImage
{
    public static function pathForEmployee(Employee $employee): ?string
    {
        $path = $employee->profile_image;
        if (! is_string($path)
            || ! preg_match('#^employees/profile-images/[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|webp)$#i', $path)) {
            return null;
        }

        return Storage::disk('public')->exists($path) ? $path : null;
    }

    public static function pathFor(User $user): ?string
    {
        foreach ([$user->employee?->profile_image, $user->profile_image] as $path) {
            if (! is_string($path)
                || ! preg_match('#^(?:employees|users)/profile-images/[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|webp)$#i', $path)) {
                continue;
            }

            if (Storage::disk('public')->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function urlFor(User $user): ?string
    {
        return self::pathFor($user) ? route('profile.image') : null;
    }
}
