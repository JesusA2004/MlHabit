<?php

namespace App\Services\Web;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Serializa un paginador de Laravel con filas transformadas en el formato
 * que entienden DataView/DataPagination (`LengthAwarePaginator` plano).
 */
final class Paginated
{
    /**
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $paginator
     * @param  callable(TItem): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    public static function of(LengthAwarePaginator $paginator, callable $map): array
    {
        $data = [];
        foreach ($paginator->items() as $item) {
            $data[] = $map($item);
        }

        return [
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'next_page_url' => $paginator->currentPage() < $paginator->lastPage() ? $paginator->url($paginator->currentPage() + 1) : null,
            'prev_page_url' => $paginator->currentPage() > 1 ? $paginator->url($paginator->currentPage() - 1) : null,
        ];
    }
}
