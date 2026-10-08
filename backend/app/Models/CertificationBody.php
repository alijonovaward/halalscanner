<?php

namespace App\Models;

use Database\Factories\CertificationBodyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'country_code', 'trust_status', 'website', 'notes'])]
class CertificationBody extends Model
{
    /** @use HasFactory<CertificationBodyFactory> */
    use HasFactory;

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
