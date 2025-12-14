<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Http\Requests\StorePostRequest;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        
        // retorna exatamente os dados usados em cada card do home (swipes)
        return Post::select([
            'id',
            'user_id',
            'caption',
            'thumbnail_path',
        ])->with([
            'firstMedia' => function($query) {
                $query->select('post_id', 'file_path');
            },
            'user' => function ($query) {
                $query->select('id', 'name', 'avatar');
            }
        ])->withCount([
            'media as image_count' => function ($query) {
                $query->where('media_type', 'image');
            },
            'media as video_count' => function ($query) {
                $query->where('media_type', 'video');
            }
        ])->paginate(16);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {
        
        $validated = $request->validated();

        Log::debug("debug", $validated);

        $newPost = Post::create($validated);

        $tags = $request->input('tags', []);

        $tagIds = collect($tags)->map(function ($tagName) {

            return Tag::firstOrCreate(['name' => $tagName])->id;
        });

        $newPost->tag()->attach($tagIds);

        return $newPost;
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        return $post->load(['media', 'comment.user:id,name', 'tag', 'user'])
            ->loadCount([
                'media as image_count' => function ($query) {
                    $query->where('media_type', 'image');
                },
                'media as video_count' => function ($query) {
                    $query->where('media_type', 'video');
                }
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        //
    }
}
