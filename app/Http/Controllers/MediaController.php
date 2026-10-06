<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Media $media)
    {
        $media->load('post:id,user_id');

        if (!$media->post || $media->post->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $deleted = $media->delete();

        return response()->json(['result' => $deleted]);
    }
}
