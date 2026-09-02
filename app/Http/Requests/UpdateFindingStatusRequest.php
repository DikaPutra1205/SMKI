<?php

namespace App\Http\Requests;

use App\Models\Finding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFindingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $finding = $this->route('finding') ?? $this->route('id');

        if ($finding instanceof Finding) {
            return $this->user()?->can('updateStatus', $finding) ?? false;
        }

        if (is_numeric($finding)) {
            $findingModel = Finding::find($finding);
            if ($findingModel) {
                return $this->user()?->can('updateStatus', $findingModel) ?? false;
            }
        }

        return $this->user()?->isAdmin() || $this->user()?->isSuperAdmin() || $this->user()?->hasPermissionTo('finding.update-status') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:open,in_progress,resolved,closed',
            'catatan' => 'nullable|string|max:2000',
            'admin_id' => 'sometimes|nullable|exists:users,id',
        ];
    }
}
