<?php

namespace App\Providers;

use App\Services\AI\AgentLoop;
use App\Services\AI\AIManager;
use App\Services\AI\LearnModeService;
use App\Services\Connections\ConnectionManager;
use App\Services\Tools\CurrentDateTimeTool;
use App\Services\Tools\HeadlessBrowserTool;
use App\Services\Tools\HttpRequestTool;
use App\Services\Tools\MailTool;
use App\Services\Tools\MemoryReadTool;
use App\Services\Tools\MemoryWriteTool;
use App\Services\Tools\ReadSkillTool;
use App\Services\Tools\SchedulerTool;
use App\Services\Tools\SendFileTool;
use App\Services\Tools\StartLearnModeTool;
use App\Services\Tools\StopLearnModeTool;
use App\Services\Tools\ToolRegistry;
use App\Services\Tools\UpdateSkillTool;
use App\Services\Tools\WebFetchTool;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AIManager::class, fn ($app) => new AIManager($app));

        $this->app->singleton(ToolRegistry::class, function ($app) {
            $registry = new ToolRegistry;
            $registry->register(new MemoryReadTool);
            $registry->register(new MemoryWriteTool);
            $registry->register(new HeadlessBrowserTool);
            $registry->register(new WebFetchTool);
            $registry->register(new HttpRequestTool);
            $registry->register(new CurrentDateTimeTool);
            $registry->register(new SendFileTool);
            $registry->register(new StartLearnModeTool);
            $registry->register(new StopLearnModeTool($app->make(LearnModeService::class)));
            $registry->register(new ReadSkillTool);
            $registry->register(new UpdateSkillTool);
            $registry->register(new MailTool(new ConnectionManager));
            $registry->register(new SchedulerTool);

            return $registry;
        });

        $this->app->singleton(AgentLoop::class, fn ($app) => new AgentLoop(
            $app->make(AIManager::class),
            $app->make(ToolRegistry::class),
        ));

        $this->app->singleton(LearnModeService::class, fn ($app) => new LearnModeService(
            $app->make(AIManager::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
