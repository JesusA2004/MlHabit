<?php

namespace App\Cms\Layout;

/**
 * Retícula del editor visual. El dueño acomoda los bloques en escritorio
 * (12 columnas sobre un lienzo de referencia de 1280 px con 40 px de margen
 * lateral, filas de 24 px); tableta y móvil se calculan automáticamente.
 */
final class Grid
{
    public const COLUMNS = 12;

    public const TABLET_COLUMNS = 8;

    public const ROW_PX = 24;

    public const REFERENCE_WIDTH = 1280;

    public const CONTENT_WIDTH = 1200;

    public const GAP_PX = 24;

    /** Fila máxima donde puede empezar un bloque. */
    public const MAX_ROW = 400;

    /** Alto máximo de un bloque, en filas. */
    public const MAX_HEIGHT = 200;

    public const MAX_BLOCKS = 60;

    public const MAX_SECTIONS = 40;

    public const MAX_Z = 999;

    public const LAYOUT_VERSION = 1;
}
