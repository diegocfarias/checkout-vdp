<?php

namespace App\Filament\Resources\IssuerResource\Pages;

use App\Filament\Resources\IssuerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIssuers extends ListRecords
{
    protected static string $resource = IssuerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
