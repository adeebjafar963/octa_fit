<?php

namespace App\Http\Resources;

use App\Services\FriendshipService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $status = FriendshipService::RELATION_NONE;

        if ($viewer !== null) {
            $status = app(FriendshipService::class)->relationStatus($viewer, $this->resource);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->profile?->avatar_path,
            'friendship_status' => $status,
        ];
    }
}
