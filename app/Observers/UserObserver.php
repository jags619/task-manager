<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Activity;
use App\Events\ActivityCreated;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        //
        $activity = Activity::create([
            'user_id' => $user->id,
            'task_id' => null,
            'action'  => 'user_registered',
            'meta'    => [
                'name'  => $user->name,
                'email' => $user->email,
            ]
        ]);

        event(new ActivityCreated($activity));
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
