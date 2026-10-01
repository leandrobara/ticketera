<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Api\BaseAPIController;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\Show;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends BaseAPIController
{
    public function index(Request $request): array
    {
        return $this->getSuccessResponse($request->user()->memberships()->where('is_active', true)->whereHas('organization')->with('organization')->get());
    }

    public function create(Request $request, ImageHelper $imageHelper): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);
        unset($data['image']);
        $imagePath = $request->hasFile('image')
            ? $imageHelper->uploadImage($request->file('image'), 'organizations')
            : null;

        try {
            $organization = DB::transaction(function () use ($data, $request, $imagePath) {
                $organization = new Organization($data + ['image_path' => $imagePath]);
                $organization->creator_user_id = $request->user()->id;
                $organization->save();
                $organization->memberships()->create(['user_id' => $request->user()->id, 'roles' => ['leader'], 'joined_at' => now()]);

                return $organization;
            });
        } catch (\Throwable $exception) {
            if ($imagePath) {
                Storage::disk('s3')->delete($imagePath);
            }

            throw $exception;
        }

        return $this->getSuccessResponse($organization);
    }

    public function update(Request $request, Organization $organization, ImageHelper $imageHelper): array
    {
        $membership = $request->user()->memberships()->where('organization_id', $organization->id)->where('is_active', true)->firstOrFail();
        abort_unless($membership->managesOrganization(), 403);
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'remove_image' => 'sometimes|boolean',
        ]);
        unset($data['image'], $data['remove_image']);
        $oldImagePath = $organization->image_path;
        $newImagePath = $request->hasFile('image')
            ? $imageHelper->uploadImage($request->file('image'), 'organizations')
            : null;
        if ($newImagePath || $request->boolean('remove_image')) {
            $data['image_path'] = $newImagePath;
        }

        try {
            $organization->update($data);
        } catch (\Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('s3')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($oldImagePath && $oldImagePath !== $organization->image_path) {
            Storage::disk('s3')->delete($oldImagePath);
        }

        return $this->getSuccessResponse($organization);
    }

    public function delete(Request $request, Organization $organization): array
    {
        abort_unless((int) $organization->creator_user_id === (int) $request->user()->id, 403);
        $organization->delete();

        return $this->getSuccessResponse(null);
    }

    public function team(): array
    {
        return $this->getSuccessResponse(OrganizationMembership::where('organization_id', app(OrganizationContext::class)->id())->with('user:id,name,email')->get());
    }

    public function updateMember(Request $request, int $member): array
    {
        $membership = OrganizationMembership::where('organization_id', app(OrganizationContext::class)->id())->findOrFail($member);
        abort_if($membership->isLeader(), 422, 'El líder siempre es quien creó la organización.');
        $data = $this->validateRoles($request);
        $data += $request->validate(['is_active' => 'sometimes|boolean']);
        $membership->update($data);

        return $this->getSuccessResponse($membership->load('user:id,name,email'));
    }

    public function invitations(): array
    {
        return $this->getSuccessResponse(OrganizationInvitation::where('organization_id', app(OrganizationContext::class)->id())->latest()->get());
    }

    public function invitation(int $invitation): array
    {
        return $this->getSuccessResponse(OrganizationInvitation::where('organization_id', app(OrganizationContext::class)->id())->findOrFail($invitation));
    }

    public function invite(Request $request): array
    {
        $data = $this->validateRoles($request) + $request->validate(['max_uses' => 'required|integer|min:1|max:10000', 'expires_at' => 'nullable|date|after:now']);
        $token = Str::random(64);
        $invitation = OrganizationInvitation::create($data + ['organization_id' => app(OrganizationContext::class)->id(), 'created_by_user_id' => $request->user()->id, 'token_hash' => hash('sha256', $token), 'encrypted_token' => Crypt::encryptString($token)]);

        return $this->getSuccessResponse(['invitation' => $invitation, 'url' => url('/admin/invitation/'.$token)]);
    }

    public function updateInvitation(Request $request, int $invitation): array
    {
        $data = $this->validateRoles($request) + $request->validate([
            'max_uses' => 'required|integer|min:1|max:10000',
            'expires_at' => 'nullable|date|after:now',
        ]);
        $active = $request->validate(['is_active' => 'sometimes|boolean']);

        $updated = DB::transaction(function () use ($invitation, $data, $active) {
            $record = OrganizationInvitation::where('organization_id', app(OrganizationContext::class)->id())
                ->lockForUpdate()->findOrFail($invitation);
            abort_if($data['max_uses'] < $record->uses, 422, 'El límite de usos no puede ser menor que los usos actuales.');
            if (array_key_exists('is_active', $active)) {
                $data['revoked_at'] = $active['is_active'] ? null : ($record->revoked_at ?? now());
            }
            $record->update($data);

            return $record;
        });

        return $this->getSuccessResponse($updated);
    }

    public function deactivateInvitation(int $invitation): array
    {
        $record = OrganizationInvitation::where('organization_id', app(OrganizationContext::class)->id())->findOrFail($invitation);
        if (! $record->revoked_at) {
            $record->update(['revoked_at' => now()]);
        }

        return $this->getSuccessResponse($record);
    }

    public function deleteInvitation(int $invitation): array
    {
        OrganizationInvitation::where('organization_id', app(OrganizationContext::class)->id())->findOrFail($invitation)->delete();

        return $this->getSuccessResponse(null);
    }

    public function accept(Request $request, string $token): array
    {
        $membership = DB::transaction(function () use ($request, $token) {
            $invitation = OrganizationInvitation::where('token_hash', hash('sha256', $token))->lockForUpdate()->firstOrFail();
            abort_unless($invitation->organization && ! $invitation->revoked_at && (! $invitation->expires_at || $invitation->expires_at->isFuture()) && $invitation->uses < $invitation->max_uses, 422, 'La invitación ya no está disponible.');
            $existing = OrganizationMembership::where('organization_id', $invitation->organization_id)->where('user_id', $request->user()->id)->first();
            abort_if($existing, 422, 'Ya pertenecés a esta organización.');
            $member = OrganizationMembership::create(['organization_id' => $invitation->organization_id, 'user_id' => $request->user()->id, 'invitation_id' => $invitation->id, 'roles' => $invitation->roles, 'show_ids' => $invitation->show_ids, 'joined_at' => now()]);
            $invitation->increment('uses');

            return $member;
        });

        return $this->getSuccessResponse($membership->load('organization'));
    }

    public function register(Request $request): array
    {
        $data = $request->validate(['name' => 'required|string|max:160', 'email' => 'required|email|max:255', 'password' => 'required|string|min:10|confirmed']);
        $data['email'] = mb_strtolower(trim($data['email']));
        validator($data, ['email' => 'unique:users,email'])->validate();

        return $this->getSuccessResponse(User::create($data)->only('id', 'name', 'email'));
    }

    private function validateRoles(Request $request): array
    {
        $data = $request->validate(['roles' => 'required|array|min:1', 'roles.*' => ['required', Rule::in(OrganizationMembership::ROLES)], 'show_ids' => 'nullable|array', 'show_ids.*' => 'integer|distinct']);
        foreach ($data['show_ids'] ?? [] as $id) {
            Show::findOrFail($id);
        }
        if (in_array('administrator', $data['roles'], true)) {
            $data['show_ids'] = null;
        }

        return $data;
    }
}
