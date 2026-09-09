<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomCakeRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShowroomCakeRequestController extends Controller
{
    public function index(Request $request)
    {
        $user  = Auth::user();
        $query = ShowroomCakeRequest::with(['requestingLocation', 'creator', 'items']);

        // Non-admins see only their own branch requests
        if (! $user->isAdmin()) {
            $locationId = $user->primaryLocation()?->id;
            $query->where(function ($q) use ($locationId) {
                $q->where('requesting_location_id', $locationId)
                  ->orWhere('factory_location_id', $locationId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('location_id')) {
            $query->where('requesting_location_id', $request->location_id);
        }

        $requests   = $query->latest()->paginate(20);
        $locations  = Location::orderBy('name')->get();
        $statusEnum = ShowroomCakeRequestStatus::cases();

        return view('sales.showroom-cake-requests.index', compact('requests', 'locations', 'statusEnum'));
    }

   public function create()
{
    $user = Auth::user();

    $branch = $user->primaryLocation();

    $factories = Location::query()
        ->where('type', 'factory')
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    return view(
        'sales.showroom-cake-requests.create',
        compact('branch', 'factories')
    );
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'needed_by'             => ['nullable', 'date'],
            'notes'                 => ['nullable', 'string', 'max:2000'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.cake_type'     => ['required', 'string', 'max:100'],
            'items.*.cake_size'     => ['nullable', 'string', 'max:50'],
            'items.*.flavor'        => ['nullable', 'string', 'max:100'],
            'items.*.shape'         => ['nullable', 'string', 'max:50'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $user = Auth::user();
        $branch = $user->primaryLocation();

        // Find any factory location
       $factory = Location::query()
    ->where('type', 'factory')
    ->where('is_active', true)
    ->first();

        DB::transaction(function () use ($validated, $user, $branch, $factory) {
            $showroomRequest = ShowroomCakeRequest::create([
                'request_number'         => ShowroomCakeRequest::generateNumber(),
                'requesting_location_id' => $branch?->id,
                'factory_location_id'    => $factory?->id,
                'status'                 => ShowroomCakeRequestStatus::Draft,
                'needed_by'              => $validated['needed_by'] ?? null,
                'notes'                  => $validated['notes'] ?? null,
                'created_by'             => $user->id,
            ]);

            foreach ($validated['items'] as $item) {
                ShowroomCakeRequestItem::create([
                    'showroom_cake_request_id' => $showroomRequest->id,
                    'cake_type'                => $item['cake_type'],
                    'cake_size'                => $item['cake_size'] ?? null,
                    'flavor'                   => $item['flavor'] ?? null,
                    'shape'                    => $item['shape'] ?? null,
                    'quantity'                 => $item['quantity'],
                    'notes'                    => $item['notes'] ?? null,
                ]);
            }
        });

        return redirect()->route('showroom-cake-requests.index')
            ->with('success', 'تم إنشاء طلب المعرض بنجاح.');
    }

    public function show(ShowroomCakeRequest $showroomCakeRequest)
    {
        $showroomCakeRequest->load(['requestingLocation', 'factoryLocation', 'creator', 'handledBy', 'items']);
        $allowedTransitions = $showroomCakeRequest->status->allowedTransitions();
        return view('sales.showroom-cake-requests.show', compact('showroomCakeRequest', 'allowedTransitions'));
    }

    public function updateStatus(Request $request, ShowroomCakeRequest $showroomCakeRequest)
    {
        $request->validate([
            'status'        => ['required', 'string'],
            'factory_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $newStatus = ShowroomCakeRequestStatus::from($request->status);

        if (! $showroomCakeRequest->canTransitionTo($newStatus)) {
            return back()->with('error', 'لا يمكن الانتقال إلى هذه الحالة.');
        }

        $data = ['status' => $newStatus];

        if ($request->filled('factory_notes')) {
            $data['factory_notes'] = $request->factory_notes;
        }
        if ($newStatus === ShowroomCakeRequestStatus::Submitted) {
            $data['submitted_at'] = now();
        }
        if ($newStatus === ShowroomCakeRequestStatus::Fulfilled) {
            $data['fulfilled_at']  = now();
            $data['handled_by']    = Auth::id();
        }

        $showroomCakeRequest->update($data);

        return back()->with('success', 'تم تحديث حالة الطلب.');
    }

    public function destroy(ShowroomCakeRequest $showroomCakeRequest)
    {
        if (! in_array($showroomCakeRequest->status, [ShowroomCakeRequestStatus::Draft, ShowroomCakeRequestStatus::Cancelled])) {
            return back()->with('error', 'لا يمكن حذف طلب إلا في حالة المسودة أو الملغى.');
        }
        $showroomCakeRequest->delete();
        return redirect()->route('showroom-cake-requests.index')->with('success', 'تم حذف الطلب.');
    }
}
