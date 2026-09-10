<?php

namespace App\Models;

use App\Services\{EmailDeliveryErrorSanitizer, EmailTemplateRegistry};
use Illuminate\Database\Eloquent\Model;

class EmailDeliveryLog extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_QUEUED => 'En attente',
            self::STATUS_PROCESSING => 'En cours',
            self::STATUS_SENT => 'Envoyé',
            self::STATUS_FAILED => 'Échec',
            self::STATUS_CANCELLED => 'Annulé',
            self::STATUS_EXPIRED => 'Expiré',
        ];
    }

    public static function templateLabels(): array
    {
        return [
            'contact_admin' => 'Contact - notification admin',
            'contact_confirmation' => 'Contact - confirmation utilisateur',
            'email_verification' => 'Compte - vérification e-mail',
            'password_reset' => 'Compte - réinitialisation du mot de passe',
            'password_changed' => 'Compte - mot de passe modifié',
            'claim_received' => 'Revendication - reçue',
            'claim_approved' => 'Revendication - acceptée',
            'claim_rejected' => 'Revendication - refusée',
            'legacy_account_migration' => 'Compte - migration historique',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function templateLabel(): string
    {
        if (isset(self::templateLabels()[$this->template_key])) {
            return self::templateLabels()[$this->template_key];
        }

        return app(EmailTemplateRegistry::class)->all()[$this->template_key]['name'] ?? $this->template_key;
    }

    public function safeErrorMessage(): ?string
    {
        return filled($this->error_message) ? EmailDeliveryErrorSanitizer::text($this->error_message) : null;
    }
}
