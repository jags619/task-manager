<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Observers\TaskObserver;
use App\Observers\UserObserver;
use App\Observers\ProjectObserver;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Activity;
use App\Events\ActivityCreated;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //Vite::prefetch(concurrency: 3);
        Task::observe(TaskObserver::class);
        User::observe(UserObserver::class);   
        Project::observe(ProjectObserver::class); 

        // ✅ login/logout
        Event::listen(Login::class, function ($event) {
            $activity = Activity::create([
                'user_id' => $event->user->id,
                'task_id' => null,
                'action'  => 'user_loggedin',
                'meta'    => [
                    'name'  => $event->user->name,
                    'email' => $event->user->email,
                ]
            ]);

            event(new ActivityCreated($activity));
        });

        Event::listen(Logout::class, function ($event) {
            $activity = Activity::create([
                'user_id' => $event->user->id,
                'task_id' => null,
                'action'  => 'user_loggedout',
                'meta'    => [
                    'name'  => $event->user->name,
                ]
            ]);

            event(new ActivityCreated($activity));
        });
    }
}
