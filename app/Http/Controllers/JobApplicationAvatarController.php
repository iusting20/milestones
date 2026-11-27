<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Serve a cached Micah Bare PNG for a job. Downloads from DiceBear on the first hit. */
class JobApplicationAvatarController extends Controller
{
    public function __invoke(string $token, JobApplication $application): Response
    {
        $path = $application->avatarCachePath();
        $disk = Storage::disk('local');
        $cached = $disk->exists($path) ? $disk->get($path) : null;

        if (! $this->isCachedPng($cached)) {
            $png = $this->download($application);
            abort_unless($this->isCachedPng($png), 502, 'Avatar unavailable.');
            $disk->put($path, $png);
            $cached = $png;
        }

        return response($cached, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    private function download(JobApplication $application): ?string
    {
        $response = Http::timeout(10)->get($application->dicebearSourceUrl());
        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    private function isCachedPng(?string $bytes): bool
    {
        return is_string($bytes)
            && strlen($bytes) > 100
            && str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
    }
}
