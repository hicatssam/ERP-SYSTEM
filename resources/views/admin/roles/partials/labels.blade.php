@php
    use App\Support\Authorization\PermissionLabels;

    $groupLabels = PermissionLabels::groupLabels();
    $permissionLabels = PermissionLabels::permissionLabels();
    $roleLabels = PermissionLabels::roleLabels();
@endphp
