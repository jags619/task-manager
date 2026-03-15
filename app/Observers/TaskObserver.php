<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Activity;
use App\Events\ActivityCreated;
use App\Events\TaskMoved;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        //
        $activity = Activity::create([
            'user_id' => auth()->id(),
            'task_id' => $task->id,
            'action' => 'task_created',
            'meta' => [
                    'task_name' => $task->name,
                    'project_name'=>$task->project->name,
                    
                ]
        ]);

        event(new ActivityCreated($activity));
        //dd('event fired');
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        //
        //dd($task->wasChanged('completed'));
        //dd($task);
       $changes = $task->getChanges();
        //dd($changes);
       if ($task->wasChanged('project_id')) {

            $oldProjectId = $task->getOriginal('project_id');
            $oldProject = Project::find($oldProjectId);
            $activity = Activity::create([
                'user_id' => auth()->id(),
                'task_id' => $task->id,
                'action' => 'task_moved',
                'meta' => [
                    'task_name' => $task->name,
                    'from' => $oldProject?->name,
                    'to' => $task->project->name
                ]
            ]);
            event(new ActivityCreated($activity));
            event(new TaskMoved($activity));
        }

        if ($task->wasChanged('due_date')) {

            $activity = Activity::create([
                'user_id' => auth()->id(),
                'task_id' => $task->id,
                'action' => 'due_date_changed',
                'meta' => [
                    'task_name' => $task->name,
                    'project_name' => $task->project->name,
                    'from' => $task->getOriginal('due_date')?->toDateString(),
                    'to' => $task->due_date->toDateString()
                ]
            ]);
            event(new ActivityCreated($activity));
            event(new TaskMoved($activity));
        }

        if ($task->wasChanged('priority')){
            $activity = Activity::create([
                'user_id' => auth()->id(),
                'task_id' => $task->id,
                'action' => 'task_reordered',
                'meta' => [
                    'task_name' => $task->name,
                    'project_name' => $task->project->name,
                    'new_order' => $task->priority,
                    'old_order' => $task->getOriginal('priority')
                ]
            ]);
            event(new ActivityCreated($activity));
            event(new TaskMoved($activity));
        }


        if(isset($changes['completed'])){
            $action = $task->completed
                ? 'task_completed'
                : 'task_uncompleted';

            $activity = Activity::create([
                'user_id'=>auth()->id(),
                'task_id'=>$task->id,
                'action'=>$action,
                 'meta'=>[
                    'task_name' => $task->name,
                    'project_name'=>$task->project->name,
                    
                ]
            ]);

            event(new ActivityCreated($activity));
        }

        if(isset($changes['name'])){

            $activity = Activity::create([
                'user_id'=>auth()->id(),
                'task_id'=>$task->id,
                'action'=>'task_renamed',
                'meta'=>[
                    'from'=>$task->getOriginal('name'),
                    'to'=>$task->name
                ]
            ]);

            event(new ActivityCreated($activity));
        }

       
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleting(Task $task): void
    {
        //
        //dd($task);
    
       $activity = Activity::create([
            'user_id' => $task->user_id ?? auth()->id(),
            'task_id' => $task->id,
            'action' => 'task_deleted',
            'meta' => [
                    'task_name' => $task->name,
                    'project_name'=>$task->project->name,
                    
                ]
        ]);
        
        event(new ActivityCreated($activity));
    }

    /**
     * Handle the Task "restored" event.
     */
    public function restored(Task $task): void
    {
        //
    }

    /**
     * Handle the Task "force deleted" event.
     */
    public function forceDeleted(Task $task): void
    {
        //
    }
}
