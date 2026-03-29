<?php

namespace App\Console\Commands;

use App\Jobs\RefreshShowcaseRoute;
use App\Models\ShowcaseRoute;
use Illuminate\Console\Command;

class RefreshShowcase extends Command
{
    protected $signature = 'showcase:refresh {--route= : ID de uma rota específica}';

    protected $description = 'Atualiza os preços das rotas da vitrine buscando a data mais barata';

    public function handle(): int
    {
        $routeId = $this->option('route');

        $query = ShowcaseRoute::where('is_active', true);

        if ($routeId) {
            $query->where('id', $routeId);
        }

        $routes = $query->get();

        if ($routes->isEmpty()) {
            $this->info('Nenhuma rota ativa encontrada.');

            return self::SUCCESS;
        }

        $this->info("Despachando refresh para {$routes->count()} rota(s)...");

        foreach ($routes as $index => $route) {
            RefreshShowcaseRoute::dispatch($route)->delay(now()->addSeconds($index * 5));
            $this->info("  [{$route->id}] {$route->routeLabel()} — agendado para " . ($index * 5) . 's');
        }

        $this->info('Jobs despachados com sucesso.');

        return self::SUCCESS;
    }
}
