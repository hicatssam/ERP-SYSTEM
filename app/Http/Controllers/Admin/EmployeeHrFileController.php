<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeHrFileController extends Controller
{
    public function show(Request $request, Employee $employee): View
    {
        $this->assertScope($request, $employee);

        return view('admin.employees.hr-file', [
            'employee' => $employee->load([
                'hrProfile', 'documents', 'employeeLocations.location',
                'currentOrgAssignment.department', 'currentOrgAssignment.position',
                'currentOrgAssignment.costCenter', 'currentOrgAssignment.manager',
                'orgAssignments.department', 'orgAssignments.position',
                'orgAssignments.costCenter', 'orgAssignments.manager',
            ]),
        ]);
    }

    public function profile(Request $request, Employee $employee): RedirectResponse
    {
        $this->assertScope($request, $employee);

        $data = $request->validate([
            'emergency_name' => ['nullable', 'string', 'max:190'],
            'emergency_phone' => ['nullable', 'string', 'max:40'],
            'emergency_relationship' => ['nullable', 'string', 'max:100'],
            'contract_ends_on' => ['nullable', 'date'],
            'onboarded_on' => ['nullable', 'date'],
            'offboarded_on' => ['nullable', 'date', 'after_or_equal:onboarded_on'],
            'lifecycle_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $employee->hrProfile()->updateOrCreate(['employee_id' => $employee->id], $data);

        return back()->with('success', 'تم تحديث الملف الوظيفي.');
    }

    public function upload(Request $request, Employee $employee): RedirectResponse
    {
        $this->assertScope($request, $employee);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'kind' => ['required', Rule::in(['contract', 'identity', 'certificate', 'other'])],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('employee-documents/'.$employee->id, 'local');

        try {
            $employee->documents()->create([
                'title' => $data['title'],
                'kind' => $data['kind'],
                'expires_on' => $data['expires_on'] ?? null,
                'notes' => $data['notes'] ?? null,
                'path' => $path,
                'original_name' => basename($file->getClientOriginalName()),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'تم حفظ المستند في الملفات الخاصة.');
    }

    public function download(
        Request $request,
        Employee $employee,
        EmployeeDocument $document
    ): StreamedResponse {
        $this->assertScope($request, $employee);
        abort_unless((int) $document->employee_id === (int) $employee->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    private function assertScope(Request $request, Employee $employee): void
    {
        abort_unless(
            Employee::query()->accessibleBy($request->user())->whereKey($employee->id)->exists(),
            403,
            'لا يمكنك عرض بيانات موظف من فرع آخر.'
        );
    }
}
