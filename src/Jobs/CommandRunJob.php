<?php
declare(strict_types=1);

namespace Tkachikov\LaravelPulse\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Tkachikov\LaravelPulse\Services\ScheduleService;
use Tkachikov\LaravelPulse\Jobs\Middleware\LockMiddleware;

class CommandRunJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use Dispatchable;
    use SerializesModels;
    use InteractsWithQueue;

    /**
     * @return void
     */
    public function __construct(
        protected string $command,
        protected array $args = [],
    ) {
    }

    /**
     * @param ScheduleService $scheduleService
     *
     * @throws mixed
     *
     * @return void
     */
    public function handle(ScheduleService $scheduleService): void
    {
        $commandInfo = $scheduleService->getForClass($this->command);
        if (
            !method_exists($commandInfo['object'], 'runInManual')
            || $commandInfo['object']->runInManual()
        ) {
            Artisan::call($this->command, $this->args);
        }
    }

    /**
     * @return array
     */
    public function middleware(): array
    {
        return [
            new LockMiddleware(),
        ];
    }

    /**
     * @return string
     */
    public function uniqueId(): string
    {
        return str($this->command)
            ->classBasename()
            ->prepend('_')
            ->toString();
    }

    /**
     * @param Throwable $e
     *
     * @return void
     */
    public function failed(Throwable $e): void
    {
        /** @var ScheduleService $service */
        $service = app(ScheduleService::class);
        $service->updateWaitingRun($this->command, $e->getMessage());
    }
}
