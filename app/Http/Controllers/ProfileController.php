<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show()
    {
        return $this->edit();
    }

    public function edit()
    {
        $user = Auth::user();

        $user->load([
            'employee.employeeLocations.location',
            'roles',
        ]);

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'profile_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
            'remove_profile_image' => [
                'nullable',
                'boolean',
            ],
        ], [
            'profile_image.image' => 'الملف المحدد يجب أن يكون صورة.',
            'profile_image.mimes' => 'الصورة يجب أن تكون بصيغة JPG أو PNG أو WEBP.',
            'profile_image.max'   => 'حجم الصورة يجب ألا يتجاوز 2MB.',
        ]);

        $user = Auth::user();
        $employee = $user->employee;

        if (
            ! $request->hasFile('profile_image') &&
            ! $request->boolean('remove_profile_image')
        ) {
            return back()->with(
                'info',
                'لم يتم اختيار صورة جديدة.'
            );
        }

        DB::transaction(function () use (
            $request,
            $user,
            $employee
        ) {
            if ($request->boolean('remove_profile_image')) {
                $this->deleteExistingImages(
                    $user->profile_image,
                    $employee?->profile_image
                );

                $user->update([
                    'profile_image' => null,
                ]);

                if ($employee) {
                    $employee->update([
                        'profile_image' => null,
                    ]);
                }
            }

            if ($request->hasFile('profile_image')) {
                $this->deleteExistingImages(
                    $user->profile_image,
                    $employee?->profile_image
                );

                $imagePath = $request
                    ->file('profile_image')
                    ->store('employees/profile-images', 'public');

                $user->update([
                    'profile_image' => $imagePath,
                ]);

                if ($employee) {
                    $employee->update([
                        'profile_image' => $imagePath,
                    ]);
                }
            }
        });

        return back()->with(
            'success',
            'تم تحديث الصورة الشخصية بنجاح.'
        );
    }

    private function deleteExistingImages(
        ?string $userImage,
        ?string $employeeImage
    ): void {
        $images = collect([
            $userImage,
            $employeeImage,
        ])
            ->filter()
            ->unique();

        foreach ($images as $image) {
            if (Storage::disk('public')->exists($image)) {
                Storage::disk('public')->delete($image);
            }
        }
    }
}