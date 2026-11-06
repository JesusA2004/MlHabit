<?php

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use App\Http\Requests\Help\UpdateTourStateRequest;
use App\Models\User;
use App\Models\UserTourState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avance de los recorridos guiados de la persona con sesión (JSON). Cada
 * quien solo lee y escribe el suyo: no hay ids de usuario en la URL.
 */
class TourProgressController extends Controller
{
    /** Recorridos terminados u omitidos: `{ tours: { "admin.general": {...} } }`. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['tours' => $this->states($this->user($request))]);
    }

    public function update(UpdateTourStateRequest $request, string $tour): JsonResponse
    {
        $user = $this->user($request);

        UserTourState::query()->updateOrCreate(
            ['user_id' => $user->id, 'tour' => $tour],
            [
                'status' => $request->string('status')->toString(),
                'last_step' => $request->filled('step') ? $request->integer('step') : null,
            ],
        );

        return response()->json(['tours' => $this->states($user)]);
    }

    /** Olvida el avance (para volver a ver el recorrido de bienvenida). */
    public function destroy(Request $request): JsonResponse
    {
        $user = $this->user($request);

        UserTourState::query()->where('user_id', $user->id)->delete();

        return response()->json(['tours' => (object) []]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @return array<string, array{status: string, step: int|null, updatedAt: string|null}>|object
     */
    private function states(User $user): array|object
    {
        $states = UserTourState::query()
            ->where('user_id', $user->id)
            ->orderBy('tour')
            ->get()
            ->mapWithKeys(fn (UserTourState $state) => [
                $state->tour => [
                    'status' => $state->status,
                    'step' => $state->last_step,
                    'updatedAt' => $state->updated_at?->toIso8601String(),
                ],
            ])
            ->all();

        // Un objeto vacío (no `[]`) para que el cliente siempre reciba un mapa.
        return $states === [] ? (object) [] : $states;
    }
}
