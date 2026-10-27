<?php

namespace App\Http\Requests\Projects;

use App\Enums\MediaVisibility;
use App\Models\Media;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Vincula un archivo YA subido (sesión de subida privada) a una entidad.
 * Solo archivos privados y subidos por quien los vincula (o con
 * media.manage), para que nadie adjunte archivos ajenos. La autorización de
 * la entidad se valida en el controlador (Policy).
 *
 * Roles permitidos por contexto vía `roles` (definidos por el controlador).
 */
class AttachFileRequest extends FormRequest
{
    /** @var list<string> */
    public const PROJECT_ROLES = ['document', 'contract', 'render', 'photo', 'client_document'];

    /** @var list<string> */
    public const DESIGN_ROLES = ['render', 'file'];

    /** @var list<string> */
    public const MEASUREMENT_ROLES = ['photo', 'sketch'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'media_id' => ['required', 'integer', Rule::exists('media', 'id')->whereNull('deleted_at')],
            'role' => ['required', 'string', Rule::in($this->allowedRoles())],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $media = Media::query()->find($this->integer('media_id'));
            if ($media === null) {
                return;
            }
            if ($media->visibility !== MediaVisibility::Private) {
                $validator->errors()->add('media_id', 'Este archivo debe subirse como privado.');
            }
            $user = $this->user();
            if ($media->uploaded_by !== $user?->id && ! ($user?->hasPermission('media.manage') ?? false)) {
                $validator->errors()->add('media_id', 'Solo puedes adjuntar archivos que tú subiste.');
            }
        });
    }

    /** @return list<string> */
    protected function allowedRoles(): array
    {
        $route = (string) $this->route()?->getName();

        return match (true) {
            str_contains($route, 'disenos') => self::DESIGN_ROLES,
            str_contains($route, 'mediciones') => self::MEASUREMENT_ROLES,
            default => self::PROJECT_ROLES,
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['media_id' => 'archivo', 'role' => 'tipo de archivo', 'caption' => 'descripción'];
    }
}
