<?php

namespace App\Filament\Resources\ShowcaseRouteResource\Pages;

use App\Filament\Resources\ShowcaseRouteResource;
use App\Services\UnsplashService;
use Filament\Resources\Pages\EditRecord;

class EditShowcaseRoute extends EditRecord
{
    protected static string $resource = ShowcaseRouteResource::class;

    protected function afterSave(): void
    {
        $record = $this->record;

        if (empty($record->image_url)) {
            $unsplash = app(UnsplashService::class);
            $photo = $unsplash->searchCityPhoto($record->arrival_city, $record->image_search_query);

            if ($photo) {
                $record->update([
                    'image_url' => $photo['url'],
                    'image_credit' => $photo['credit'],
                ]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
