<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentProofReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewAll = $user->isAdmin() || $user->can('financial.global.view');

        $locationIds = $canViewAll
            ? Location::query()->pluck('id')
            : collect([$user->primaryLocation()?->id])->filter();

        $query = Payment::query()
            ->with([
                'paymentMethod',
                'location',
                'locationPaymentAccount',
                'latestProofAnalysis',
            ])
            ->whereIn('location_id', $locationIds)
            ->whereNotNull('payment_proof')
            ->where('payment_proof', '!=', '');

        if ($request->filled('payment_status')) {
            $query->where(
                'status',
                $request->string('payment_status')->toString()
            );
        }

        if ($request->filled('risk_level')) {
            $risk = $request->string('risk_level')->toString();

            if ($risk === 'not_analyzed') {
                $query->whereDoesntHave('latestProofAnalysis');
            } elseif (in_array($risk, ['low', 'medium', 'high', 'unknown'], true)) {
                $query->whereHas('latestProofAnalysis', function ($analysisQuery) use ($risk): void {
                    $analysisQuery->where('risk_level', $risk);
                });
            }
        }

        if ($request->filled('analysis_status')) {
            $analysisStatus = $request->string('analysis_status')->toString();

            if (in_array($analysisStatus, ['pending', 'processing', 'completed', 'failed'], true)) {
                $query->whereHas('latestProofAnalysis', function ($analysisQuery) use ($analysisStatus): void {
                    $analysisQuery->where('status', $analysisStatus);
                });
            }
        }

        if ($request->filled('location_id') && $canViewAll) {
            $requestedLocationId = (int) $request->input('location_id');

            if ($locationIds->contains($requestedLocationId)) {
                $query->where('location_id', $requestedLocationId);
            }
        }

        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());

            if ($term !== '') {
                $query->where(function ($search) use ($term): void {
                    $search->where('id', ctype_digit($term) ? (int) $term : -1)
                        ->orWhere('reference_number', 'like', '%' . $term . '%')
                        ->orWhereHas('latestProofAnalysis', function ($analysis) use ($term): void {
                            $analysis->where('sender_name', 'like', '%' . $term . '%')
                                ->orWhere('sender_account', 'like', '%' . $term . '%')
                                ->orWhere('transaction_reference', 'like', '%' . $term . '%');
                        });
                });
            }
        }

        $payments = $query
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $locations = $canViewAll
            ? Location::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('finance.payments.proof-review', [
            'payments' => $payments,
            'locations' => $locations,
            'canViewAll' => $canViewAll,
            'aiEnabled' => (bool) config('services.payment_proof_ai.enabled'),
        ]);
    }
}
