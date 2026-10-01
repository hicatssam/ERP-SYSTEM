@extends('layouts.app')

@section('title', 'الهيكل الوظيفي')
@section('page-title', 'الهيكل الوظيفي')

@section('content')
<div class="org-page">
    <div class="page-actions">
        <div>
            <h1 class="page-heading">الهيكل الوظيفي ومراكز التكلفة</h1>
            <p class="page-subheading">رتّب الأقسام والوظائف ومسؤوليات الموظفين حسب الفرع. يحتفظ النظام بتاريخ كل تكليف ويستخدمه في تقارير الرواتب.</p>
        </div>
        <a class="btn btn-outline" href="{{ route('hr.organization.csv') }}">تصدير توزيع الموظفين CSV</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="org-stats">
        <div class="card card-body"><small>الأقسام</small><strong>{{ $departments->count() }}</strong></div>
        <div class="card card-body"><small>المسميات الوظيفية</small><strong>{{ $positions->count() }}</strong></div>
        <div class="card card-body"><small>مراكز التكلفة</small><strong>{{ $centers->count() }}</strong></div>
        <div class="card card-body"><small>موظفون دون تكليف حالي</small><strong>{{ $unassignedCount }}</strong></div>
    </div>

    @can('hr.organization.manage')
        <div class="card card-body org-actions">
            <div><strong>إضافة وتنظيم</strong><p class="page-subheading">أضف القسم أولًا، ثم الوظيفة ومركز التكلفة، وبعدها عيّن الموظف بتاريخ سريان.</p></div>
            <div class="org-buttons">
                <button class="btn btn-outline" type="button" data-org-open="orgDepartment">إضافة قسم</button>
                <button class="btn btn-outline" type="button" data-org-open="orgPosition">إضافة وظيفة</button>
                <button class="btn btn-outline" type="button" data-org-open="orgCenter">إضافة مركز تكلفة</button>
                <button class="btn btn-gold" type="button" data-org-open="orgAssignment">تكليف موظف</button>
            </div>
        </div>
    @endcan

    <div class="org-catalog">
        <section class="card">
            <div class="card-header"><span class="card-title">الأقسام</span></div>
            <div class="card-body org-list">
                @forelse($departments as $department)
                    <div><strong>{{ $department->name }}</strong><small>{{ $department->code }} · {{ $department->location?->name ?? 'جميع الفروع' }} · {{ $departmentCounts[$department->id] ?? 0 }} موظف
                        @if($department->parent) · يتبع {{ $department->parent->name }} @endif</small></div>
                @empty <p>لم تُضف أقسام بعد.</p> @endforelse
            </div>
        </section>
        <section class="card">
            <div class="card-header"><span class="card-title">مراكز التكلفة</span></div>
            <div class="card-body org-list">
                @forelse($centers as $center)
                    <div><strong>{{ $center->name }}</strong><small>{{ $center->code }} · {{ $center->location?->name ?? 'جميع الفروع' }} · {{ $centerCounts[$center->id] ?? 0 }} موظف</small></div>
                @empty <p>لم تُضف مراكز تكلفة بعد.</p> @endforelse
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card-header"><span class="card-title">الموظفون وتكليفاتهم الحالية</span></div>
        <form method="GET" action="{{ route('hr.organization.index') }}" class="card-body org-search">
            <label class="form-label" for="orgSearch">بحث باسم الموظف</label>
            <input class="form-input" type="search" id="orgSearch" name="q" value="{{ $search }}" maxlength="190" placeholder="ابحث عن موظف...">
            <button class="btn btn-outline" type="submit">بحث</button>
            @if($search !== '')<a class="btn btn-ghost" href="{{ route('hr.organization.index') }}">مسح</a>@endif
        </form>
        <div class="org-table-wrap">
            <table class="org-table">
                <thead><tr><th>الموظف</th><th>القسم والوظيفة</th><th>مركز التكلفة</th><th>المدير المباشر</th><th>تاريخ السريان</th><th></th></tr></thead>
                <tbody>
                    @forelse($employees as $employee)
                        @php($assignment = $employee->currentOrgAssignment)
                        <tr>
                            <td><strong>{{ $employee->full_name }}</strong><small>{{ $employee->employee_number }}</small></td>
                            <td>{{ $assignment?->department?->name ?? 'غير محدد' }}<small>{{ $assignment?->position?->name ?? $employee->job_title ?? '—' }}</small></td>
                            <td>{{ $assignment?->costCenter?->name ?? 'غير محدد' }}</td>
                            <td>{{ $assignment?->manager?->full_name ?? '—' }}</td>
                            <td>{{ $assignment?->effective_from?->format('Y-m-d') ?? '—' }}</td>
                            <td>
                                @can('hr.organization.manage')
                                @if($employee->isActive())
                                    <button class="btn btn-outline btn-sm" type="button" data-org-open="orgAssignment" data-org-employee="{{ $employee->id }}">تكليف جديد</button>
                                @endif
                                @endcan
                                @can('hr.documents.view')<a href="{{ route('hr.employees.file', $employee) }}">سجل الموظف</a>@endcan
                            </td>
                        </tr>
                    @empty <tr><td colspan="6">لا يوجد موظفون مطابقون.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $employees->links() }}</div>
    </section>
</div>

@can('hr.organization.manage')
    <dialog class="org-modal" id="orgDepartment" aria-labelledby="orgDepartmentTitle">
        <form action="{{ route('hr.departments.store') }}" method="POST" class="org-form">
            @csrf <input type="hidden" name="_form" value="orgDepartment">
            <div class="org-modal-head"><h2 id="orgDepartmentTitle">إضافة قسم</h2><button type="button" data-org-close aria-label="إغلاق">×</button></div>
            <div><label class="form-label">رمز القسم</label><input class="form-input" name="code" maxlength="30" value="{{ old('_form') === 'orgDepartment' ? old('code') : '' }}" required></div>
            <div><label class="form-label">اسم القسم</label><input class="form-input" name="name" maxlength="190" value="{{ old('_form') === 'orgDepartment' ? old('name') : '' }}" required></div>
            <div><label class="form-label">الفرع</label><select class="form-input" name="location_id">
                @if(auth()->user()->isAdmin() || auth()->user()->can('employees.view_all'))<option value="">جميع الفروع</option>@endif
                @foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('_form') === 'orgDepartment' && old('location_id') == $location->id)>{{ $location->name }}</option>@endforeach
            </select></div>
            <div><label class="form-label">قسم أعلى (اختياري)</label><select class="form-input" name="parent_id"><option value="">لا يوجد</option>
                @foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('_form') === 'orgDepartment' && old('parent_id') == $department->id)>{{ $department->name }}</option>@endforeach
            </select></div>
            <div class="org-modal-actions"><button type="button" class="btn btn-ghost" data-org-close>إلغاء</button><button class="btn btn-gold">حفظ القسم</button></div>
        </form>
    </dialog>

    <dialog class="org-modal" id="orgPosition" aria-labelledby="orgPositionTitle">
        <form action="{{ route('hr.positions.store') }}" method="POST" class="org-form">
            @csrf <input type="hidden" name="_form" value="orgPosition">
            <div class="org-modal-head"><h2 id="orgPositionTitle">إضافة وظيفة</h2><button type="button" data-org-close aria-label="إغلاق">×</button></div>
            <div><label class="form-label">رمز الوظيفة</label><input class="form-input" name="code" maxlength="30" value="{{ old('_form') === 'orgPosition' ? old('code') : '' }}" required></div>
            <div><label class="form-label">المسمى الوظيفي</label><input class="form-input" name="name" maxlength="190" value="{{ old('_form') === 'orgPosition' ? old('name') : '' }}" required></div>
            <div><label class="form-label">القسم</label><select class="form-input" name="department_id" required><option value="">اختر القسم</option>
                @foreach($departments->where('is_active', true) as $department)<option value="{{ $department->id }}" @selected(old('_form') === 'orgPosition' && old('department_id') == $department->id)>{{ $department->name }}</option>@endforeach
            </select></div>
            <div><label class="form-label">الدرجة (اختياري)</label><input class="form-input" name="grade" maxlength="40" value="{{ old('_form') === 'orgPosition' ? old('grade') : '' }}"></div>
            <div class="org-modal-actions"><button type="button" class="btn btn-ghost" data-org-close>إلغاء</button><button class="btn btn-gold">حفظ الوظيفة</button></div>
        </form>
    </dialog>

    <dialog class="org-modal" id="orgCenter" aria-labelledby="orgCenterTitle">
        <form action="{{ route('hr.cost-centers.store') }}" method="POST" class="org-form">
            @csrf <input type="hidden" name="_form" value="orgCenter">
            <div><div class="org-modal-head"><h2 id="orgCenterTitle">إضافة مركز تكلفة</h2><button type="button" data-org-close aria-label="إغلاق">×</button></div></div>
            <div><label class="form-label">رمز المركز</label><input class="form-input" name="code" maxlength="30" value="{{ old('_form') === 'orgCenter' ? old('code') : '' }}" required></div>
            <div><label class="form-label">اسم المركز</label><input class="form-input" name="name" maxlength="190" value="{{ old('_form') === 'orgCenter' ? old('name') : '' }}" required></div>
            <div><label class="form-label">الفرع</label><select class="form-input" name="location_id">
                @if(auth()->user()->isAdmin() || auth()->user()->can('employees.view_all'))<option value="">جميع الفروع</option>@endif
                @foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('_form') === 'orgCenter' && old('location_id') == $location->id)>{{ $location->name }}</option>@endforeach
            </select></div>
            <div class="org-modal-actions"><button type="button" class="btn btn-ghost" data-org-close>إلغاء</button><button class="btn btn-gold">حفظ المركز</button></div>
        </form>
    </dialog>

    <dialog class="org-modal" id="orgAssignment" aria-labelledby="orgAssignmentTitle">
        <form action="{{ route('hr.assignments.store') }}" method="POST" class="org-form">
            @csrf <input type="hidden" name="_form" value="orgAssignment">
            <div class="org-modal-head"><h2 id="orgAssignmentTitle">تكليف موظف</h2><button type="button" data-org-close aria-label="إغلاق">×</button></div>
            <p class="page-subheading">يسري التكليف من التاريخ المحدد. يُغلق التكليف السابق تلقائيًا في اليوم الذي يسبقه، ويظل محفوظًا في سجل الموظف.</p>
            <div><label class="form-label">الموظف</label><select class="form-input" id="orgEmployee" name="employee_id" required><option value="">اختر الموظف</option>
                @foreach($staffOptions as $staff)<option value="{{ $staff->id }}" data-location="{{ $staff->employeeLocations->first()?->location_id }}" @selected(old('_form') === 'orgAssignment' && old('employee_id') == $staff->id)>{{ $staff->full_name }} ({{ $staff->employee_number }})</option>@endforeach
            </select></div>
            <div><label class="form-label">القسم</label><select class="form-input" id="orgAssignedDepartment" name="department_id" required><option value="">اختر القسم</option>
                @foreach($departments->where('is_active', true) as $department)<option value="{{ $department->id }}" data-location="{{ $department->location_id }}" @selected(old('_form') === 'orgAssignment' && old('department_id') == $department->id)>{{ $department->name }} · {{ $department->location?->name ?? 'عام' }}</option>@endforeach
            </select></div>
            <div><label class="form-label">الوظيفة</label><select class="form-input" id="orgAssignedPosition" name="position_id"><option value="">دون وظيفة محددة</option>
                @foreach($positions->where('is_active', true) as $position)<option value="{{ $position->id }}" data-department="{{ $position->department_id }}" @selected(old('_form') === 'orgAssignment' && old('position_id') == $position->id)>{{ $position->name }}</option>@endforeach
            </select></div>
            <div><label class="form-label">مركز التكلفة</label><select class="form-input" id="orgAssignedCenter" name="cost_center_id"><option value="">دون مركز محدد</option>
                @foreach($centers->where('is_active', true) as $center)<option value="{{ $center->id }}" data-location="{{ $center->location_id }}" @selected(old('_form') === 'orgAssignment' && old('cost_center_id') == $center->id)>{{ $center->name }} · {{ $center->location?->name ?? 'عام' }}</option>@endforeach
            </select></div>
            <div><label class="form-label">المدير المباشر</label><select class="form-input" id="orgAssignedManager" name="manager_employee_id"><option value="">دون مدير مباشر</option>
                @foreach($staffOptions as $staff)<option value="{{ $staff->id }}" data-location="{{ $staff->employeeLocations->first()?->location_id }}" @selected(old('_form') === 'orgAssignment' && old('manager_employee_id') == $staff->id)>{{ $staff->full_name }}</option>@endforeach
            </select></div>
            <div><label class="form-label">تاريخ السريان</label><input class="form-input" type="date" name="effective_from" value="{{ old('_form') === 'orgAssignment' ? old('effective_from') : now()->toDateString() }}" required></div>
            <div><label class="form-label">سبب النقل أو التكليف (اختياري)</label><textarea class="form-input" name="reason" maxlength="500" rows="2">{{ old('_form') === 'orgAssignment' ? old('reason') : '' }}</textarea></div>
            <div class="org-modal-actions"><button type="button" class="btn btn-ghost" data-org-close>إلغاء</button><button class="btn btn-gold">حفظ التكليف</button></div>
        </form>
    </dialog>
@endcan

<style>
.org-page{max-width:1300px;margin:auto}.org-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem;margin:1rem 0}.org-stats small,.org-list small,.org-table small{display:block;color:var(--text-muted)}.org-stats strong{display:block;font-size:1.4rem;margin-top:.35rem}.org-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.org-actions p{margin:.3rem 0 0}.org-buttons{display:flex;flex-wrap:wrap;gap:.4rem}.org-catalog{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}.org-list{max-height:280px;overflow:auto}.org-list>div{padding:.7rem 0;border-bottom:1px solid var(--border)}.org-list>div:last-child{border:0}.org-search{display:flex;align-items:end;flex-wrap:wrap;gap:.6rem}.org-search .form-input{min-width:220px}.org-table-wrap{overflow:auto}.org-table{width:100%;border-collapse:collapse}.org-table th,.org-table td{padding:.8rem;text-align:right;border-bottom:1px solid var(--border);white-space:nowrap}.org-table th{color:var(--text-muted);font-size:.8rem}.org-table td{font-size:.85rem}.org-modal{width:min(560px,calc(100vw - 28px));max-height:min(86vh,820px);overflow:auto;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:16px;padding:0;box-shadow:0 20px 65px #0006}.org-modal::backdrop{background:#0009}.org-form{display:grid;gap:.9rem;padding:1.3rem}.org-form .form-input{width:100%}.org-modal-head,.org-modal-actions{display:flex;align-items:center;justify-content:space-between;gap:.6rem}.org-modal-head h2{font-size:1.2rem;margin:0}.org-modal-head button{border:0;background:transparent;color:inherit;font-size:1.7rem;cursor:pointer}.org-modal-actions{justify-content:flex-end;padding-top:.4rem}@media(max-width:900px){.org-stats{grid-template-columns:repeat(2,1fr)}.org-actions,.org-catalog{display:block}.org-actions>*{margin-bottom:.6rem}.org-catalog>.card{margin-bottom:1rem}}@media(max-width:500px){.org-stats{grid-template-columns:1fr 1fr}}
</style>
<script>
document.querySelectorAll('[data-org-open]').forEach(button => button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.orgOpen);
    if (!dialog) return;
    if (button.dataset.orgEmployee) {
        document.getElementById('orgEmployee').value = button.dataset.orgEmployee;
        document.getElementById('orgEmployee').dispatchEvent(new Event('change'));
    }
    dialog.showModal();
    dialog.querySelector('input:not([type="hidden"]), select')?.focus();
}));
document.querySelectorAll('dialog.org-modal').forEach(dialog => {
    dialog.querySelectorAll('[data-org-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
});
const orgDepartmentSelect = document.getElementById('orgAssignedDepartment');
const orgPositionSelect = document.getElementById('orgAssignedPosition');
const orgEmployeeSelect = document.getElementById('orgEmployee');
orgEmployeeSelect?.addEventListener('change', () => {
    const branch = orgEmployeeSelect.selectedOptions[0]?.dataset.location ?? '';
    for (const select of [orgDepartmentSelect, document.getElementById('orgAssignedCenter'), document.getElementById('orgAssignedManager')]) {
        for (const option of select.options) {
            if (!option.value) continue;
            option.hidden = select.id === 'orgAssignedManager'
                ? (!branch || option.dataset.location !== branch || option.value === orgEmployeeSelect.value)
                : Boolean(option.dataset.location && option.dataset.location !== branch);
        }
        if (select.selectedOptions[0]?.hidden) select.value = '';
    }
    orgDepartmentSelect.dispatchEvent(new Event('change'));
});
orgDepartmentSelect?.addEventListener('change', () => {
    for (const option of orgPositionSelect.options) {
        if (!option.value) continue;
        option.hidden = option.dataset.department !== orgDepartmentSelect.value;
    }
    if (orgPositionSelect.selectedOptions[0]?.hidden) orgPositionSelect.value = '';
});
orgEmployeeSelect?.dispatchEvent(new Event('change'));
@if($errors->any() && in_array(old('_form'), ['orgDepartment','orgPosition','orgCenter','orgAssignment'], true))
document.getElementById(@json(old('_form')))?.showModal();
@endif
</script>
@endsection
