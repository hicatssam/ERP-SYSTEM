<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Notifications\PaymentMethodCreatedNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Storage;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::withTrashed()->orderBy('sort_order')->get();
        return view('admin.payment-methods.index', compact('methods'));
    }

    public function create()
    {
        return view('admin.payment-methods.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'name_ar'     => 'required|string|max:100',
            'code'        => 'required|string|max:50|unique:payment_methods,code',
            'type'        => 'required|in:cash,electronic_wallet,bank_transfer,card_pos,other',
            'sort_order'  => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'api_key'     => 'nullable|string|max:500',
            'notes'       => 'nullable|string',
            'is_active'   => 'boolean',
            'requires_verification' => 'boolean',
            'requires_reference'    => 'boolean',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('payment-methods', 'public');
        }

        $method = PaymentMethod::create([
            'name'                   => $validated['name'],
            'name_ar'                => $validated['name_ar'],
            'code'                   => strtoupper($validated['code']),
            'type'                   => $validated['type'],
            'sort_order'             => $validated['sort_order'] ?? 99,
            'description'            => $validated['description'] ?? null,
            'api_key'                => $validated['api_key'] ?? null,
            'notes'                  => $validated['notes'] ?? null,
            'is_active'              => $request->boolean('is_active', true),
            'requires_verification'  => $request->boolean('requires_verification'),
            'requires_reference'     => $request->boolean('requires_reference'),
            'logo_path'              => $logoPath,
            'logo'                   => $logoPath ? Storage::url($logoPath) : null,
        ]);

        NotificationDispatcher::notifyByPermissions(
            new PaymentMethodCreatedNotification($method),
            ['payment_methods.view', 'payment_methods.manage'],
            null,
            ['payment_methods.manage'],
            Auth::id(),
        );

        ActivityLogger::log(
            userId:     Auth::id(),
            action:     'payment_method.created',
            module:     'settings',
            recordType: 'payment_methods',
            recordId:   $method->id,
            newValues:  ['name' => $method->name, 'code' => $method->code],
        );

        return redirect()->route('payment-methods.index')
            ->with('success', "تم إضافة طريقة الدفع «{$method->name_ar}» بنجاح.");
    }

    public function show(PaymentMethod $paymentMethod)
    {
        return redirect()->route('payment-methods.edit', $paymentMethod);
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return view('admin.payment-methods.edit', compact('paymentMethod'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'name_ar'     => 'required|string|max:100',
            'code'        => 'required|string|max:50|unique:payment_methods,code,' . $paymentMethod->id,
            'type'        => 'required|in:cash,electronic_wallet,bank_transfer,card_pos,other',
            'sort_order'  => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'api_key'     => 'nullable|string|max:500',
            'notes'       => 'nullable|string',
            'is_active'   => 'boolean',
            'requires_verification' => 'boolean',
            'requires_reference'    => 'boolean',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $updates = [
            'name'                   => $validated['name'],
            'name_ar'                => $validated['name_ar'],
            'code'                   => strtoupper($validated['code']),
            'type'                   => $validated['type'],
            'sort_order'             => $validated['sort_order'] ?? $paymentMethod->sort_order,
            'description'            => $validated['description'] ?? null,
            'api_key'                => $validated['api_key'] ?? null,
            'notes'                  => $validated['notes'] ?? null,
            'is_active'              => $request->boolean('is_active', true),
            'requires_verification'  => $request->boolean('requires_verification'),
            'requires_reference'     => $request->boolean('requires_reference'),
        ];

        if ($request->hasFile('logo')) {
            // Remove old logo
            if ($paymentMethod->logo_path) {
                Storage::disk('public')->delete($paymentMethod->logo_path);
            }
            $logoPath = $request->file('logo')->store('payment-methods', 'public');
            $updates['logo_path'] = $logoPath;
            $updates['logo']      = Storage::url($logoPath);
        }

        $paymentMethod->update($updates);

        ActivityLogger::log(
            userId:     Auth::id(),
            action:     'payment_method.updated',
            module:     'settings',
            recordType: 'payment_methods',
            recordId:   $paymentMethod->id,
            newValues:  ['name' => $paymentMethod->name],
        );

        return redirect()->route('payment-methods.index')
            ->with('success', 'تم تحديث طريقة الدفع بنجاح.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();

        ActivityLogger::log(
            userId:     Auth::id(),
            action:     'payment_method.deleted',
            module:     'settings',
            recordType: 'payment_methods',
            recordId:   $paymentMethod->id,
        );

        return redirect()->route('payment-methods.index')
            ->with('success', 'تم حذف طريقة الدفع.');
    }

    public function toggle(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);
        $state = $paymentMethod->is_active ? 'مفعّل' : 'معطّل';
        return back()->with('success', "تم تغيير حالة طريقة الدفع إلى: {$state}");
    }
}
