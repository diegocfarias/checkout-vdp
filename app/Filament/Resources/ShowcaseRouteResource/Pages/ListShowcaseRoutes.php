<?php

namespace App\Filament\Resources\ShowcaseRouteResource\Pages;

use App\Filament\Resources\ShowcaseRouteResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListShowcaseRoutes extends ListRecords
{
    protected static string $resource = ShowcaseRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refreshAll')
                ->label('Atualizar todas')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Atualizar todas as rotas')
                ->modalDescription('Isso vai buscar os menores preços para todas as rotas ativas. Pode levar alguns minutos.')
                ->action(function () {
                    Artisan::call('showcase:refresh');
                    Notification::make()
                        ->title('Atualização agendada')
                        ->body('Todas as rotas ativas serão atualizadas em instantes.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()->label('Nova rota'),
        ];
    }
}
