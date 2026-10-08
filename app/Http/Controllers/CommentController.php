<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCommentRequest $request)
    {
        
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;

        $post = Post::findOrFail($validated['post_id']);

        $comment = $post->comment()->create($validated);

        $comment->load('user:id,name');

        return (new CommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Comment $comment)
    {
        $this->authorize('delete', $comment);

        $deleted = $comment->delete();

        return response()->json(['result' => $deleted]);
    }
}
