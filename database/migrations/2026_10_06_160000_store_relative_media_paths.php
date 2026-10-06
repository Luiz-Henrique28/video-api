<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Stores only the relative storage path instead of an absolute URL,
     * so changing host, port, domain or disk does not break existing records.
     */
    public function up(): void
    {
        $this->rewrite('media', 'file_path', fn (string $value) => $this->toRelative($value));
        $this->rewrite('post', 'thumbnail_path', fn (string $value) => $this->toRelative($value));
    }

    public function down(): void
    {
        $disk = Storage::disk(config('filesystems.default'));

        $toUrl = fn (string $value) => Str::startsWith($value, ['http://', 'https://']) ? $value : $disk->url($value);

        $this->rewrite('media', 'file_path', $toUrl);
        $this->rewrite('post', 'thumbnail_path', $toUrl);
    }

    private function toRelative(string $value): string
    {
        if (!Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return Str::contains($value, '/storage/') ? Str::after($value, '/storage/') : $value;
    }

    private function rewrite(string $table, string $column, callable $transform): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->each(function ($row) use ($table, $column, $transform) {
                $new = $transform($row->{$column});

                if ($new !== $row->{$column}) {
                    DB::table($table)->where('id', $row->id)->update([$column => $new]);
                }
            });
    }
};
