<?php

namespace App\Traits;

use App\Events\ComplaintSubmitted;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait HasComplaintSubmission
{
    public function submit(array $data, User $user, ?Request $request = null): Complaint
    {
        return DB::transaction(function () use ($data, $user, $request) {
            $ref = $this->repo->generateUniqueReference();

            $complaint = $this->repo->create([
                'reference_number' => $ref,
                'user_id'          => $user->id,
                'entity_id'        => $data['entity_id'],
                'type'             => $data['type'],
                'location'         => $data['location'] ?? null,
                'description'      => $data['description'],
                'status'           => 'new'
            ]);

            if ($request?->hasFile('files')) {
                $files = $request->file('files');
                $files = $files instanceof UploadedFile ? [$files] : $files;

                foreach ($files as $file) {
                    if ($file->isValid()) {
                        $this->handleSingleAttachment($complaint, $file);
                    }
                }
            }

            event(new ComplaintSubmitted($complaint));

            $this->audit->logAction($user->id, 'complaint_submitted', [
                'ref'         => $ref,
                'ip'          => $request?->ip(),
                'user_agent'  => $request?->userAgent()
            ]);

            return $complaint;
        });
    }

    protected function handleSingleAttachment(Complaint $complaint, UploadedFile $file): void
    {
        $storagePath = "complaints/{$complaint->reference_number}";
        $path = Storage::disk('public')->putFile($storagePath, $file);

        $complaint->attachments()->create([
            'file_path'     => $path,
            'file_name'     => $file->getClientOriginalName(),
            'file_type'     => $file->extension(),
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getClientMimeType(),
        ]);
    }
}
