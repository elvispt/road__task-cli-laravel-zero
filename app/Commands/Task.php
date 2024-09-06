<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

use function Termwind\render;

final class Task extends Command
{
    private const string FILE_NAME = 'task-cli.json';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:task
        {action=list : The action to run (required). Can be "list" "add" "update" "delete" "mark-todo" "mark-in-progress" "mark-done"}
        {--status= : Filter by status of the task when listing. Can be "todo" "in-progress" "done"}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Task management app. Stores task on a local JSON file.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tasks = $this->getTasks();
        $action = $this->argument('action');
        if ( ! $this->isActionValid($action)) {
            $this->error("Invalid action '$action'. See help for more information.");

            return;
        }

        switch ($action) {
            case 'list':
                $this->outputTasks($tasks);
                break;
            case 'add':
                $this->appendTask($tasks);
                break;
            case 'update':
                $this->updateTask($tasks);
                break;
            case 'mark-todo':
                $this->updateTaskStatus($tasks, 'todo');
                break;
            case 'mark-in-progress':
                $this->updateTaskStatus($tasks, 'in-progress');
                break;
            case 'mark-done':
                $this->updateTaskStatus($tasks, 'done');
                break;
            case 'delete':
                $this->deleteTask($tasks);
                break;
        }
    }

    private function outputTasks(Collection $tasks): void
    {
        if (count($tasks) === 0) {
            $this->info('No tasks');
        }

        $tasks->each(function ($task) {
            render(<<<HTML
                <div>
                    <div class="px-1 bg-blue-300 text-black">$task->id</div>
                    <span class="ml-1">$task->description</span>

                    <span class="ml-2 bg-green-300 text-black">$task->status</span>
                </div>
            HTML
            );
        });
    }

    private function appendTask(Collection $tasks): void
    {
        $description = $this->ask('Task description');

        if ($description === null) {
            return;
        }

        $id = $tasks->max('id') + 1;

        $task = (object)[
            'id' => $id,
            'description' => $description,
            'status' => 'todo',
            'createdAt' => Carbon::now()->format('Y-m-d H:i:s'),
            'updatedAt' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        $tasks->add($task);

        $this->saveTasks($tasks);

        $this->info("Added new task '$id'.");
    }

    private function updateTask(Collection $tasks): void
    {
        $this->outputTasks($tasks);
        $id = $this->ask('Which ID?');
        $description = $this->ask("Description for task '$id'");

        if ($description === null) {
            return;
        }

        $task = $tasks->firstWhere('id', (int)$id);
        $task->description = $description;
        $task->updatedAt = Carbon::now()->format('Y-m-d H:i:s');

        $this->saveTasks($tasks);

        $this->info("Updated task '$id'.");
    }

    private function updateTaskStatus(Collection $tasks, string $status): void
    {
        $this->outputTasks($tasks);
        $id = $this->ask('Which ID?');

        if ($id === null) {
            return;
        }

        $task = $tasks->firstWhere('id', (int)$id);
        $task->status = $status;
        $task->updatedAt = Carbon::now()->format('Y-m-d H:i:s');

        $this->saveTasks($tasks);

        $this->info("Updated task '$id' with status '$status'.");
    }

    private function deleteTask(Collection $tasks): void
    {
        $this->outputTasks($tasks);
        $id = $this->ask('Which ID?');

        if ($id === null) {
            return;
        }

        $tasks = $tasks->filter(fn($task) => $task->id !== (int)$id)->values();

        $this->outputTasks($tasks);

        $this->saveTasks($tasks);

        $this->warn("Deleted task '$id'.");
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }

    protected function getTasks(): Collection
    {
        if ( ! file_exists(self::FILE_NAME)) {
            return collect();
        }

        $raw = file_get_contents(self::FILE_NAME);

        try {
            $tasks = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $tasks = null;
        }

        $taskList = $tasks?->tasks ?? [];

        return collect($taskList);
    }

    private function isActionValid(string $action = null): bool
    {
        return in_array($action, [
            'list',
            'add',
            'update',
            'mark-todo',
            'mark-in-progress',
            'mark-done',
            'delete',
        ]);
    }

    private function saveTasks(Collection $tasks): void
    {
        $tasks = $tasks
            ->sortByDesc('updatedAt', SORT_NATURAL)
            ->values();

        $wrapper = new \stdClass();
        $wrapper->tasks = $tasks->toArray();
        try {
            $rawJson = json_encode($wrapper, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->error("Failed to convert json encoded tasks.");
            $rawJson = null;
        }
        if ($rawJson === null) {
            return;
        }

        file_put_contents(self::FILE_NAME, $rawJson);
    }
}
