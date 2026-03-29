<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShowcaseRouteResource\Pages;
use App\Jobs\RefreshShowcaseRoute;
use App\Models\ShowcaseRoute;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ShowcaseRouteResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected static ?string $model = ShowcaseRoute::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Vitrine';

    protected static ?string $modelLabel = 'Rota da Vitrine';

    protected static ?string $pluralModelLabel = 'Vitrine de Ofertas';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        $airports = self::getAirportOptions();

        return $schema->schema([
            Section::make('Rota')->columns(2)->schema([
                Select::make('departure_iata')
                    ->label('Origem (IATA)')
                    ->options($airports)
                    ->searchable()
                    ->required(),

                TextInput::make('departure_city')
                    ->label('Cidade de origem')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('São Paulo'),

                Select::make('arrival_iata')
                    ->label('Destino (IATA)')
                    ->options($airports)
                    ->searchable()
                    ->required(),

                TextInput::make('arrival_city')
                    ->label('Cidade de destino')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('Recife'),

                Select::make('trip_type')
                    ->label('Tipo de viagem')
                    ->options([
                        'roundtrip' => 'Ida e volta',
                        'oneway' => 'Somente ida',
                    ])
                    ->default('roundtrip')
                    ->required()
                    ->live(),

                Select::make('cabin')
                    ->label('Cabine')
                    ->options([
                        'EC' => 'Econômica',
                        'EX' => 'Executiva',
                    ])
                    ->default('EC')
                    ->required(),
            ]),

            Section::make('Configuração de busca')->columns(2)->schema([
                DatePicker::make('search_date_from')
                    ->label('Data inicial')
                    ->helperText('Início do período de busca.')
                    ->minDate(now()->toDateString())
                    ->required()
                    ->displayFormat('d/m/Y'),

                DatePicker::make('search_date_to')
                    ->label('Data final')
                    ->helperText('Fim do período de busca.')
                    ->minDate(now()->toDateString())
                    ->afterOrEqual('search_date_from')
                    ->required()
                    ->displayFormat('d/m/Y'),

                TextInput::make('sample_dates_count')
                    ->label('Quantos dias pesquisar')
                    ->helperText('Quantas datas diferentes amostrar dentro do período.')
                    ->numeric()
                    ->minValue(2)
                    ->maxValue(15)
                    ->default(8)
                    ->required(),

                TextInput::make('return_stay_days')
                    ->label('Dias de estadia')
                    ->helperText('Para ida e volta: dias entre ida e volta.')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(60)
                    ->default(7)
                    ->visible(fn (Get $get) => $get('trip_type') === 'roundtrip'),
            ]),

            Section::make('Exibição')->columns(2)->schema([
                TextInput::make('sort_order')
                    ->label('Ordem de exibição')
                    ->numeric()
                    ->default(0)
                    ->helperText('Menor = aparece primeiro'),

                Toggle::make('is_active')
                    ->label('Ativa')
                    ->default(true),
            ]),

            Section::make('Imagem')->schema([
                TextInput::make('image_search_query')
                    ->label('Busca personalizada de imagem')
                    ->helperText('Texto para buscar no Unsplash. Se vazio, busca pontos turísticos da cidade de destino.')
                    ->maxLength(255)
                    ->placeholder('Ex: Cristo Redentor, Praia de Copacabana...')
                    ->columnSpanFull(),

                ViewField::make('image_picker')
                    ->view('filament.components.showcase-image-picker')
                    ->columnSpanFull(),

                TextInput::make('image_url')
                    ->label('URL da imagem')
                    ->maxLength(500)
                    ->placeholder('Será preenchido ao selecionar uma imagem acima')
                    ->columnSpanFull(),

                TextInput::make('image_credit')
                    ->label('Crédito da imagem')
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('image_zoom')
                    ->label('Zoom da imagem (%)')
                    ->helperText('100 = normal, 150 = 1.5x zoom. Mínimo 100, máximo 200.')
                    ->numeric()
                    ->minValue(100)
                    ->maxValue(200)
                    ->default(100)
                    ->suffix('%'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('route')
                    ->label('Rota')
                    ->getStateUsing(fn (ShowcaseRoute $record) => $record->routeLabel())
                    ->weight('bold')
                    ->searchable(query: function ($query, string $search) {
                        $query->where('departure_iata', 'like', "%{$search}%")
                            ->orWhere('arrival_iata', 'like', "%{$search}%")
                            ->orWhere('departure_city', 'like', "%{$search}%")
                            ->orWhere('arrival_city', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('arrival_city')
                    ->label('Destino')
                    ->sortable(),

                Tables\Columns\TextColumn::make('trip_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'roundtrip' ? 'Ida e volta' : 'Somente ida')
                    ->color(fn (string $state) => $state === 'roundtrip' ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('cached_price')
                    ->label('Menor preço')
                    ->formatStateUsing(fn (?string $state) => $state ? 'R$ ' . number_format((float) $state, 2, ',', '.') : '—')
                    ->color('success')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cached_airline')
                    ->label('Cia')
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : '—')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('cached_date')
                    ->label('Data mais barata')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('last_refreshed_at')
                    ->label('Atualizado')
                    ->since()
                    ->placeholder('Nunca')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Actions\EditAction::make()->label('Editar'),
                Actions\Action::make('refresh')
                    ->label('Atualizar preço')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Atualizar preço')
                    ->modalDescription('Isso vai buscar o menor preço atual na API. Pode levar até 2 minutos.')
                    ->action(function (ShowcaseRoute $record) {
                        RefreshShowcaseRoute::dispatch($record);
                        Notification::make()
                            ->title('Busca de preço agendada')
                            ->body("A rota {$record->routeLabel()} será atualizada em instantes.")
                            ->success()
                            ->send();
                    }),
                Actions\DeleteAction::make()->label('Excluir'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShowcaseRoutes::route('/'),
            'create' => Pages\CreateShowcaseRoute::route('/create'),
            'edit' => Pages\EditShowcaseRoute::route('/{record}/edit'),
        ];
    }

    private static function getAirportOptions(): array
    {
        $path = public_path('data/airports.json');
        if (! file_exists($path)) {
            return [];
        }

        $airports = json_decode(file_get_contents($path), true);
        if (! is_array($airports)) {
            return [];
        }

        $options = [];
        foreach ($airports as $airport) {
            $code = $airport['c'] ?? '';
            $desc = $airport['d'] ?? $code;
            if ($code) {
                $options[$code] = $desc;
            }
        }

        return $options;
    }
}
