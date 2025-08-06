<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaRequest;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Log::debug("entrou no index ?");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMediaRequest $request)
    {

        $validated = $request->validated();

        $post = Post::findOrFail($validated['post_id']);

        foreach ($request->file('files') as $index => $file) {

            $fileName = $file->getClientOriginalName();
            $newFileName = uniqid() . "" . $fileName;

            $filePath = $file->storeAs("uploads/users/{$request->user_id}", $newFileName);

            $fileModel[] = [
                'file_path' => $filePath,
                'media_type' => $file->getMimeType() === 'video/mp4' ? 'video' : 'image',
                'order' => $index,
            ];
        }

        $post->media()->createMany($fileModel);

        return response()->json([
            'success' => 'true',
            'result' => $post->load('media')
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Media $media)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Media $media)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Media $media)
    {
        //
    }
}
