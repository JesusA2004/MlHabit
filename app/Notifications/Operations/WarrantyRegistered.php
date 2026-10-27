<?php

namespace App\Notifications\Operations;

use App\Models\Project;
use App\Models\Warranty;

/** Al cliente: se registraron las garantías de su proyecto. */
class WarrantyRegistered extends OperationsNotification
{
    /**
     * @param  list<Warranty>  $warranties
     */
    public function __construct(public Project $project, public array $warranties) {}

    protected function kind(): string
    {
        return 'warranty_registered';
    }

    protected function title(): string
    {
        return 'Tus garantías están registradas';
    }

    protected function lines(): array
    {
        $lines = ['Registramos las garantías de tu proyecto «'.$this->project->name.'»:'];

        foreach ($this->warranties as $warranty) {
            $lines[] = '• '.$warranty->type->label().' ('.$warranty->folio.'), vigente hasta el '.self::spanishDate($warranty->ends_on).'.';
        }

        $lines[] = 'Consulta el alcance y las condiciones en tu portal.';

        return $lines;
    }

    protected function actionLabel(): string
    {
        return 'Ver mis garantías';
    }

    protected function actionUrl(object $notifiable): string
    {
        return url('/portal');
    }

    protected function tone(): string
    {
        return 'success';
    }

    protected function icon(): string
    {
        return 'badge-check';
    }
}
