<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Family;
use Illuminate\Database\Eloquent\Model;

class WriteAudit
{
    public function handle(Family $family, string $actor, Model $entity, string $action, array $before = []): void
    {
        AuditLog::create([
            'family_id' => $family->id, 'actor_user_id' => $actor,
            'entity_type' => $entity->getTable(), 'entity_id' => $entity->getKey(), 'action' => $action,
            'changes_ciphertext' => ['before' => $before, 'after' => $entity->attributesToArray()],
        ]);
    }
}
