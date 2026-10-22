<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A private file (payment slip, cheque image...) stored outside public/ and served only through an authorised route. */
class DocumentAttachment extends Model
{
    public const KINDS = ['slip' => 'Payment slip', 'cheque_image' => 'Cheque image', 'deposit_slip' => 'Deposit slip', 'supporting' => 'Supporting document'];

    protected $guarded = ['id'];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Store an upload privately (storage/app/private/...) and record it against a model. */
    public static function store(Model $owner, UploadedFile $file, string $kind = 'slip'): self
    {
        $folder = 'attachments/'.strtolower(class_basename($owner)).'/'.now()->format('Y/m');
        $path = $file->store($folder, 'local');

        return $owner->attachments()->create([
            'kind' => $kind,
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => auth()->id(),
        ]);
    }

    /** Copy an existing attachment to another owner (submission → payment) without duplicating the file. */
    public function copyTo(Model $owner): self
    {
        return $owner->attachments()->create($this->only(['kind', 'disk', 'path', 'original_name', 'mime', 'size', 'sha256', 'uploaded_by']));
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /** Validation rules for uploads, from settings. */
    public static function rules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'mimes:'.implode(',', config('distributor.upload_mimes')), 'max:'.(int) (config('distributor.max_upload_mb') * 1024)];
    }
}
