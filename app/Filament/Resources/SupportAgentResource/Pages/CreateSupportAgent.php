<?php

namespace App\Filament\Resources\SupportAgentResource\Pages;

use App\Filament\Resources\SupportAgentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportAgent extends CreateRecord
{
    protected static string $resource = SupportAgentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = 'support';

        return $data;
    }
}
