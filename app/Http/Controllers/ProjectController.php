<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
   public function store(Request $request)
   {

    // dd($request->all());
    $request->validate([
        'name' => 'required|string|max:255'
    ]);
    auth()->user()->projects()->create([
        'name' => $request->name
    ]);
    

    return redirect()->route('dashboard');
   }
}
