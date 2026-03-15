<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tag;

class TagController extends Controller
{
    //
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string'
        ]);
        $data['user_id'] = auth()->id();

        auth()->user()->tags()->create($data);

        return back();
    }

    public function destroy(Tag $tag)
    {
        //dd('controller hit', $tag);
        abort_if($tag->user_id !== auth()->id(), 403);
        
        //detach pivot relations
        $tag->tasks()->detach();

        $tag->delete();
        
        return back();
    }
}
