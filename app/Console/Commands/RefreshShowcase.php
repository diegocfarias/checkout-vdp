<?php

namespace App\Console\Commands;

use App\Jobs\RefreshShowcaseRoute;
use App\Models\Setting;
use App\Models\ShowcaseRoute;
use Illuminate\Console\Command;

class RefreshShowcase extends Command
{
    protected $signature = 'showcase:refresh {--route= : ID de uma rota específica} {--force : Ignorar intervalo e forçar atualização}';

    protected $description = 'Atualiza os preços das rotas da vitrine buscando a data mais barata';

    public function handle(): int
    {
        $routeId = $this->option('route');
        $force = $this->option('force');

        $refreshMinutes = max(5, (int) Setting::get('showcase_refresh_minutes', 60));
        $waitSeconds = max(1, (int) Setting::get('showcase_wait_seconds', 10));

        $query = ShowcaseRoute::where('is_active', true);

        if ($routeId) {
            $query->where('id', $routeId);
        }

        $routes = $query->get();

        if (! $force && ! $routeId) {
            $routes = $routes->filter(function (ShowcaseRoute $route) use ($refreshMinutes) {
                if (! $route->last_refreshed_at) {
                    return true;
                }

                return $route->last_refreshed_at->lte(now()->subMinutes($refreshMinutes));
            })->values();
        }

        if ($routes->isEmpty()) {
            $this->info('Nenhuma rota precisando de atualização.');

            return self::SUCCESS;
        }

        $this->info("Despachando refresh para {$routes->count()} rota(s)...");

        foreach ($routes as $index => $route) {
            $samplesCount = max(1, $route->sample_dates_count ?? 8);
            $estimatedJobDuration = $samplesCount * ($waitSeconds + 30);
            $delay = $index * $estimatedJobDuration;

            RefreshShowcaseRoute::dispatch($route)->delay(now()->addSeconds($delay));

            $this->info("  [{$route->id}] {$route->routeLabel()} — agendado para {$delay}s (estimativa: {$estimatedJobDuration}s/job)");
        }

        $this->info('Jobs despachados com sucesso.');

        return self::SUCCESS;
    }
}
