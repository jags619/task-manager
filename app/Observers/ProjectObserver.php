<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\Activity;
use App\Events\ActivityCreated;

class ProjectObserver
{
    /**
     * Handle the Project "created" event.
     */
    public function created(Project $project): void
    {
        //
        $activity = Activity::create([
            'user_id' => auth()->id(),
            'task_id' => null,
            'action'  => 'project_created',
            'meta'    => [
                'project_name' => $project->name,
            ]
        ]);

        event(new ActivityCreated($activity));
    }

    /**
     * Handle the Project "updated" event.
     */
    public function updated(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "restored" event.
     */
    public function restored(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "force deleted" event.
     */
    public function forceDeleted(Project $project): void
    {
        //
    }
}
