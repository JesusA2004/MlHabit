<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configuración editable del proveedor Synology desde /admin/almacenamiento.
 * La ruta raíz NO es un campo de este formulario a propósito: la define el
 * administrador del servidor por variable de entorno (NAS_SFTP_ROOT), para
 * que nadie pueda apuntar el proveedor a una carpeta arbitraria desde la
 * interfaz web. La contraseña y la ruta de la llave privada son opcionales
 * al editar: en blanco significa "conservar el valor actual".
 */
class StorageProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'auth_method' => ['required', 'string', 'in:password,private_key'],
            'password' => ['nullable', 'string', 'max:255'],
            'private_key_path' => ['nullable', 'string', 'max:500'],
            'transfer_limit_kbps' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
