<?php

namespace App\Policies;

use App\Models\ReportSchedule;
use App\Models\User;

class ReportSchedulePolicy
{
    /**
     * Admins can manage any schedule.
     * Non-admins can only manage schedules scoped to their primary location.
     */
    private function ownsSchedule(User $user, ReportSchedule $schedule): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $ownLocation = $user->primaryLocation();

        return $ownLocation !== null && $schedule->location_id === $ownLocation->id;
    }

    public function view(User $user, ReportSchedule $schedule): bool
    {
        return $this->ownsSchedule($user, $schedule);
    }

    public function update(User $user, ReportSchedule $schedule): bool
    {
        return $this->ownsSchedule($user, $schedule);
    }

    public function delete(User $user, ReportSchedule $schedule): bool
    {
        return $this->ownsSchedule($user, $schedule);
    }
}
