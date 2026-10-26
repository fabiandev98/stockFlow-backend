<?php

namespace App\Http\Resources;

use App\Models\SupplierFeedbackNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierFeedbackNote */
class SupplierFeedbackNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'supplier_id' => $this->supplier_id, 'purchase_id' => $this->purchase_id, 'user_id' => $this->user_id, 'user' => new UserResource($this->whenLoaded('user')), 'rating' => $this->rating, 'issue_type' => $this->issue_type, 'note' => $this->note, 'created_at' => $this->created_at];
    }
}
