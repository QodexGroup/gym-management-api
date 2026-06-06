<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Aws\S3\S3Client;
use Illuminate\Http\Request;

class StorageController extends Controller
{
    private function getS3Client(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => 'https://' . env('R2_ACCOUNT_ID') . '.r2.cloudflarestorage.com',
            'credentials' => [
                'key' => env('R2_ACCESS_KEY_ID'),
                'secret' => env('R2_SECRET_ACCESS_KEY'),
            ],
            'use_path_style_endpoint' => true,
        ]);
    }

    public function getPresignedUrl(Request $request)
    {
        $request->validate([
            'path' => 'required|string|max:500',
            'content_type' => 'required|string|in:image/jpeg,image/jpg,image/png,image/webp,application/pdf',
        ]);

        $s3Client = $this->getS3Client();

        $cmd = $s3Client->getCommand('PutObject', [
            'Bucket' => env('R2_BUCKET'),
            'Key' => $request->path,
            'ContentType' => $request->content_type,
        ]);

        $presignedRequest = $s3Client->createPresignedRequest($cmd, '+15 minutes');

        return ApiResponse::success([
            'url' => (string) $presignedRequest->getUri(),
            'path' => $request->path,
        ]);
    }
}
