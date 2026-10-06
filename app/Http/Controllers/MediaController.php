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
        $media->loadMissing('post:id,user_id');

        $this->authorize('delete', $media);

        $deleted = $media->delete();

        return response()->json(['result' => $deleted]);
    }
}
