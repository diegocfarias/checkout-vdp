<?php

namespace App\Filament\Resources\SupportAgentResource\Pages;

use App\Filament\Resources\SupportAgentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupportAgents extends ListRecords
{
    protected static string $resource = SupportAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
