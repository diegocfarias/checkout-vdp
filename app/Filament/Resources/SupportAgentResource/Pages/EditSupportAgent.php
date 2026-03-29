<?php

namespace App\Filament\Resources\SupportAgentResource\Pages;

use App\Filament\Resources\SupportAgentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupportAgent extends EditRecord
{
    protected static string $resource = SupportAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
