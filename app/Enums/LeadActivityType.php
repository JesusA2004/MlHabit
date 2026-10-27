<?php

namespace App\Enums;

/** Tipo de entrada en la bitácora comercial de un prospecto. */
enum LeadActivityType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Note = 'note';
    case Call = 'call';
    case Message = 'message';
    case Email = 'email';
    case Assigned = 'assigned';
    case FollowUpScheduled = 'follow_up_scheduled';
    case ConsultationScheduled = 'consultation_scheduled';
    case ConsultationUpdated = 'consultation_updated';
    case PrequalificationUpdated = 'prequalification_updated';
    case DuplicateSubmission = 'duplicate_submission';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Solicitud recibida',
            self::StatusChanged => 'Cambio de etapa',
            self::Note => 'Nota',
            self::Call => 'Llamada',
            self::Message => 'Mensaje',
            self::Email => 'Correo',
            self::Assigned => 'Asignación',
            self::FollowUpScheduled => 'Seguimiento programado',
            self::ConsultationScheduled => 'Asesoría agendada',
            self::ConsultationUpdated => 'Asesoría actualizada',
            self::PrequalificationUpdated => 'Precalificación',
            self::DuplicateSubmission => 'Nueva solicitud del mismo contacto',
            self::System => 'Sistema',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Created => 'brand',
            self::StatusChanged, self::Assigned => 'info',
            self::ConsultationScheduled, self::ConsultationUpdated, self::PrequalificationUpdated => 'info',
            self::FollowUpScheduled, self::DuplicateSubmission => 'warning',
            default => 'neutral',
        };
    }

    /**
     * Tipos que el equipo puede registrar a mano desde el detalle.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Note, self::Call, self::Message, self::Email];
    }
}
