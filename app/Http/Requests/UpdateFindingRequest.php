<?php

namespace App\Http\Requests;

use App\Models\Finding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $finding = $this->route('finding') ?? $this->route('id');

        if ($finding instanceof Finding) {
            return $this->user()?->can('update', $finding) ?? false;
        }

        if (is_numeric($finding)) {
            $findingModel = Finding::find($finding);
            if ($findingModel) {
                return $this->user()?->can('update', $findingModel) ?? false;
            }
        }

        return $this->user()?->isAdmin() || $this->user()?->isSuperAdmin() || $this->user()?->hasPermissionTo('finding.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'category' => 'sometimes|in:major,minor,observasi',
            'kategori' => 'sometimes|in:major,minor,observasi',
            'deadline' => 'nullable|date',
            'catatan' => 'nullable|string|max:2000',
            'admin_notes' => 'nullable|string|max:2000',
            'catatan_admin' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'pic_id' => 'sometimes|nullable|exists:users,id',
        ];
    }
}
