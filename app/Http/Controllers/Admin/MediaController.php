<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\TenantValidation;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Ad-hoc image uploads of the admin editors (event images...).
 *
 * Files are stored per tenant under tenants/{tenant_id}/... on the public
 * disk; deletion is only allowed for files of the current tenant (its own
 * folder, or a legacy file referenced by one of its events/restaurants).
 */
class MediaController extends Controller
{
    use TenantValidation;

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:10240', // 10MB max
        ]);

        $tenantId = $this->currentTenantId();
        abort_unless($tenantId, 403);

        $file = $request->file('file');
        $path = $file->store($this->tenantFolder($tenantId).'/events', 'public');

        return response()->json([
            'success' => true,
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
        ]);
    }

    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string|max:500',
        ]);

        $path = $this->normalizePath((string) $request->input('path'));

        if ($path === null || ! $this->belongsToCurrentTenant($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ], 404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ], 404);
        }

        $disk->delete($path);

        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully',
        ]);
    }

    private function tenantFolder(int $tenantId): string
    {
        return 'tenants/'.$tenantId;
    }

    /** Relative path on the public disk, or null if it is not a safe one. */
    private function normalizePath(string $path): ?string
    {
        // Accept a full public URL as well as a disk-relative path.
        $publicUrl = rtrim(Storage::disk('public')->url(''), '/');
        if ($publicUrl !== '' && str_starts_with($path, $publicUrl)) {
            $path = substr($path, strlen($publicUrl));
        }
        $path = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        return $path;
    }

    private function belongsToCurrentTenant(string $path): bool
    {
        $tenantId = $this->currentTenantId();
        if (! $tenantId) {
            return false;
        }

        if (str_starts_with($path, $this->tenantFolder($tenantId).'/')) {
            return true;
        }

        // Another tenant's folder is never deletable.
        if (str_starts_with($path, 'tenants/')) {
            return false;
        }

        // Legacy (pre-tenancy) uploads: only if referenced by a row of this
        // tenant (queries are tenant-scoped by the global scope).
        $like = '%'.str_replace('%', '', $path).'%';
        // JSON columns may store the path with escaped slashes.
        $likeJson = str_replace('/', '\\/', $like);
        $referenced = fn ($q) => $q->where('image_url', 'like', $like)
            ->orWhere('images', 'like', $like)
            ->orWhere('images', 'like', $likeJson);

        return Event::where($referenced)->exists() || Restaurant::where($referenced)->exists();
    }
}
