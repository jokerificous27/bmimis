<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property int|null $updated_by
 */
#[Fillable(['key', 'value', 'updated_by'])]
class SystemSetting extends Model
{
    /** @use HasFactory<SystemSettingFactory> */
    use HasFactory, RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public function auditName(): string
    {
        return 'setting';
    }

    public function auditPersonnelId(): ?int
    {
        return null;
    }
}
