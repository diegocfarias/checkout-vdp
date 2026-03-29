<?php

namespace App\Filament\Resources\IssuerResource\Pages;

use App\Filament\Resources\IssuerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIssuer extends CreateRecord
{
    protected static string $resource = IssuerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = 'issuer';

        return $data;
    }
}
