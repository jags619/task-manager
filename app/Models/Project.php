<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['name', 'user_id'];
    public function tasks()
    {
        
        return $this->hasMany(Task::class);
    }

    public function orderedTasks()
    {
        return $this->hasMany(Task::class)->orderBy('priority');
    }
}
