<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }

    public function serviceCenters(): HasMany
    {
        return $this->hasMany(ServiceCenter::class);
    }
}
