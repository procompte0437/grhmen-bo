<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalActivite extends Model
{
    protected $table = 'journal_activites';

    protected $fillable = ['user_id', 'action', 'module', 'description', 'cible_type', 'cible_id', 'adresse_ip'];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Enregistre une action dans le journal d'audit. */
    public static function consigner(string $action, string $module, string $description, ?Model $cible = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'description' => mb_substr($description, 0, 250),
            'cible_type' => $cible ? class_basename($cible) : null,
            'cible_id' => $cible?->getKey(),
            'adresse_ip' => request()?->ip(),
        ]);
    }
}
