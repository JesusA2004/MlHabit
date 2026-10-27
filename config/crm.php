<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Visibilidad de prospectos
    |--------------------------------------------------------------------------
    | `all`: todo el personal con leads.view ve todos los prospectos (por
    | defecto). `assigned`: quien no tenga leads.assign solo ve los suyos y
    | los que no tienen responsable.
    */
    'lead_visibility' => env('CRM_LEAD_VISIBILITY', 'all'),

    /*
    | Límite de solicitudes públicas por IP y por hora (/solicitar).
    */
    'lead_submissions_per_hour' => (int) env('CRM_LEAD_SUBMISSIONS_PER_HOUR', 5),
];
