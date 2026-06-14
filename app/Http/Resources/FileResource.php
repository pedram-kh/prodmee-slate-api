<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slot' => $this->slot,
            'name' => $this->name,
            'label' => $this->label,
            'kind' => $this->kindFromMime(),
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            // Temporary signed URL so private S3 objects can be opened/downloaded.
            'url' => $this->signedUrl(),
        ];
    }

    private function signedUrl(): ?string
    {
        try {
            return Storage::disk('s3')->temporaryUrl($this->s3_key, now()->addMinutes(30));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function kindFromMime(): string
    {
        $type = (string) $this->mime_type;
        $name = strtolower((string) $this->name);
        if (str_starts_with($type, 'image/')) {
            return 'image';
        }
        if ($type === 'application/pdf' || str_ends_with($name, '.pdf')) {
            return 'pdf';
        }
        if (str_starts_with($type, 'video/')) {
            return 'video';
        }
        if (str_contains($type, 'presentation') || str_contains($type, 'powerpoint')
            || str_ends_with($name, '.ppt') || str_ends_with($name, '.pptx') || str_ends_with($name, '.key')) {
            return 'ppt';
        }
        return 'file';
    }
}
