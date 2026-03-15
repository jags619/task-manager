<?php

namespace App\Http\Controllers;


use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Activity;
use App\Events\ActivityCreated;

class TaskController extends Controller
{
    Public function index(Request $request)
    {
        
        
       $projectId = $request->get('project_id');
        $tasks = auth()->user()
                       ->tasks()
                       
                       ->when($projectId, function ($query) use ($projectId) {
                            $query->where('project_id', $projectId);
                        })
                       ->with('tags')
                       ->orderBy('completed') //incomplete first
                       ->orderBy('priority')
                       ->get();

        $projects = auth()->user()->projects()
                ->with([
                    'tasks' => function($q){
                        $q->orderBy('priority');
                    },
                    'tasks.tags'
                    ])
                ->withCount([
                    'tasks',
                    'tasks as completed_tasks_count' => function ($q) {
                        $q->where('completed', true);
                    }
                ])
                ->get();
        $tags = auth()->user()->tags()->get();

        $activities = Activity::latest()
                ->limit(50)
                ->get();

        return Inertia::render('Tasks/Index',[
            'tasks' => $tasks,
            'projects' => $projects,
            'projectId' => $projectId,
            'tags' => $tags,
            'activities' => $activities
        ]);
        
    }   
    
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'project_id' =>  'nullable|exists:projects,id',
            'due_date' => 'nullable|date',
            'tags' => 'array'
        ]);

        $nextPriority = auth()->user()
                    ->tasks()
                    ->where('project_id', $data['project_id'], function ($query) use ($data) {
                        $query->where('project_id', data['project_id']);
                    })
                    ->max('priority') ?? 0;

        $task = auth()->user()->tasks()->create([
            'name' => $data['name'],
            'project_id' => $data['project_id'],
            'priority' => $nextPriority + 1,
            'due_date' => $data['due_date'],
            
        ]);

        if(!empty($data['tags'])){
            $validTags = auth()->user()
                    ->tags()
                    ->whereIn('id', $data['tags'])
                    ->pluck('id');

            $task->tags()->sync($validTags);
        }       

        return redirect()->route('dashboard');
    }

    public function update(Request $request, Task $task)
    {
        
        abort_if($task->user_id !== auth()->id(), 403);
        //dd($request->all());
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'completed' => 'sometimes|boolean',
            'due_date' => 'nullable|date',
            'tags' => 'sometimes|array',
            'project_id' =>'sometimes|exists:projects,id',
        ]);

        if ($request->completed == true) {
            $data['priority'] = Task::where('project_id', $task->project_id)
                                    ->where('user_id', auth()->id())
                                    ->max('priority') + 1;
        }

        
        $task->update($data);

        if($request->has('tags')){
            $validTags = auth()->user()
                ->tags()
                ->whereIn('id', $request->tags)
                ->pluck('id');

            $task->tags()->sync($validTags);

            $activity = Activity::create([
                'user_id' => auth()->id(),
                'task_id' => $task->id,
                'action'  => 'task_tags_updated',
                'meta'    => [
                    'task_name'    => $task->name,
                    'project_name' => $task->project->name,
                    'tags'         => $task->tags()->pluck('name'),
                ]
            ]);
            //dd($activity);
            event(new ActivityCreated($activity));
        }
        

       
        //return redirect()->route('dashboard');

        return back();
    }

    public function destroy(Task $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);
        //dd('before delete', $task->id); 
        $task->user_id = auth()->id();
        $task->delete();
        return redirect('/');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        //dd($request['order']);
        foreach ($request['order'] as $index => $id){
          $task = Task::where('id', $id)
                ->where('user_id', auth()->id())
                ->first();
        
            if(!$task) continue; //skip if not found or not owned by any user
            //dd($task->priority, $index + 1, $task->getFillable(), $task->getGuarded()); 
            $task->priority = $index + 1;
            $task->save();
        }
         return redirect('/');
        //return response()->json(['status' => 'ok']);
    }

    public function search(Request $request)
    {
        $query = $request->get('q');

        $result = Task::where('user_id', auth()->id())
            ->where(function($q) use ($query){
                $q->where('name', 'like', "%{$query}" ) //task name
                    ->orWhereHas('project', function($q) use ($query){
                        $q->where('name', 'like', "%{$query}"); //project name
                    })
                    ->orWhereHas('tags', function($q) use ($query){
                        $q->where('name', 'like', "%{$query}"); //tag name
                    });
            })
            ->with(['project', 'tags'])
            ->orderBy('completed')
            ->orderBy('priority')
            ->limit(20)
            ->get();
        
        return response()->json($results);
    }
}
