<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaRequest;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\GenerateThumbFromVideo;


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
        $disk = config('filesystems.default');
        $validated = $request->validated();
        $post = Post::findOrFail($validated['post_id']);

        $fileModel = [];
        $thumbnailPath = null;
        $firstVideoPath = null;

        foreach ($request->file('files') as $index => $file) {
            // Sanitizar nome do arquivo: remover espaços e caracteres especiais
            $sanitizedFileName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $newFileName = uniqid() . "_" . $sanitizedFileName;
            $isImage = str_starts_with($file->getMimeType(), 'image/');

            $directory = "uploads/users/{$request->user_id}/posts/{$post->id}";

            // Garantir que o diretório existe
            Storage::disk($disk)->makeDirectory($directory);

            // Salvar arquivo com nome customizado
            $storagePath = $file->storeAs($directory, $newFileName, $disk);

            if ($storagePath === false) {
                throw new \Exception("Falha ao salvar arquivo: {$newFileName}");
            }

            $fileUrl = Storage::disk($disk)->url($storagePath);

            // Se for imagem e ainda não tem thumbnail, usa a primeira imagem
            if ($isImage && !$thumbnailPath) {
                $thumbnailName = "thumb_" . $newFileName;
                $thumbnailPath = $file->storeAs(
                    "uploads/users/{$request->user_id}/posts/{$post->id}/thumbnail",
                    $thumbnailName,
                    $disk
                );

                $post->update(['thumbnail_path' => Storage::disk($disk)->url($thumbnailPath)]);
            }
            // Se for vídeo e ainda não tem vídeo salvo, guarda para gerar thumbnail depois
            elseif ($firstVideoPath === null && !$isImage) {
                $firstVideoPath = $storagePath;
            }

            $fileModel[] = [
                'file_path' => $fileUrl,
                'media_type' => $file->getMimeType() === 'video/mp4' ? 'video' : 'image',
                'order' => $index,
            ];
        }

        // Se não tem thumbnail e tem vídeo, dispara job para gerar thumbnail do vídeo
        if (!$thumbnailPath && $firstVideoPath) {
            GenerateThumbFromVideo::dispatch($post->user_id, $post->id, $firstVideoPath);
        }

        $post->media()->createMany($fileModel);

        return response()->json([
            'success' => true,
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
