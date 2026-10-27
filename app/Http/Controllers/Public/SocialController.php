<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicSiteDataService;
use App\Studio\StudioPagePresenter;
use Inertia\Inertia;
use Inertia\Response;

class SocialController extends Controller
{
    public function __invoke(PublicSiteDataService $siteData, StudioPagePresenter $studio): Response
    {
        return Inertia::render('public/Redes', [
            ...$siteData->base(),
            'studio' => $studio->published('redes'),
        ]);
    }
}
