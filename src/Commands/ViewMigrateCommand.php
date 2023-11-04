<?php

namespace Jhavenz\AutoMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Psy\Sudo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ViewMigrateCommand extends Command
{
    protected $signature = 'rtn:migrate-view 
                                        {table_name : The name of the table the view will create/drop}
                                        {--command=up : The migration command to execute ("up" or "down")} 
                                        {--connection= : The connection to run this migration on}';

    protected $description = "
        Migrate a database view using the 'create_*_view naming convention for the migration file. 
        The default command is 'up' - to create the view, or use --command=down to delete.
    ";

    /** @var Collection<SplFileInfo> */
    private Collection $migrationFiles;

    public function handle(): int
    {
        if (! $this->hasAValidMigrationCommand()) {
            return self::FAILURE;
        }

        if (! $this->setMigrationFiles()) {
            return self::FAILURE;
        }

        return $this->runMigrations();
    }

    private function setMigrationFiles(): bool
    {
        $this->migrationFiles = collect(
            Finder::create()
                ->in(database_path('migrations'))
                ->files()
                ->name(sprintf(
                    '*%s_view*.php',
                    $tableName = str($this->argument('table_name'))->slug()->replace('-', '_')->toString()
                ))
        )->values();

        if ($this->migrationFiles->count() < 1) {
            $this->error("No migration file was found using table name: [{$tableName}]. Exiting..");

            return false;
        }

        $this->info("Running [{$this->option('command')}]");

        $this->table(
            ['Migration File', 'Connection'],
            $this->migrationFiles->map(function (SplFileInfo $fileInfo) {
                return [
                    'Migration File' => basename($fileInfo->getRealPath(), '.php'),
                    'Connection' => $this->connectionFor(
                        Sudo::callMethod(app('migrator'), 'resolvePath', $fileInfo->getRealPath())
                    ),
                ];
            })->all(),
        );

        return true;
    }

    private function connectionFor(Migration $migration): ?string
    {
        return $this->option('connection') ?? $migration->getConnection();
    }

    private function isCreatingViews(): bool
    {
        return $this->option('command') === 'up';
    }

    private function isDroppingViews(): bool
    {
        return $this->option('command') === 'down';
    }

    private function prepareMigrationRepository(Migrator $migrator, SplFileInfo $fileInfo): void
    {
        if (! $migrator->repositoryExists()) {
            $this->info('No migrations table was found, created it.');

            $migrator->getRepository()->createRepository();
        } elseif ($this->isCreatingViews() && in_array(
            $fileInfo->getBasename('.php'),
            $migrator->getRepository()->getRan()
        )) {
            $migrator->getRepository()->delete(
                new class($fileInfo) extends Migration
                {
                    public string $migration;

                    public function __construct(
                        SplFileInfo $fileInfo
                    ) {
                        $this->migration = $fileInfo->getBasename('.php');
                    }
                }
            );
        }
    }

    private function hasAValidMigrationCommand(): bool
    {
        return tap($this->isCreatingViews() || $this->isDroppingViews(), function (bool $valid) {
            ! $valid && $this->error(
                sprintf("[%s] is not a valid migration command. Options are: 'up' (to create) or 'down' (to drop)", $this->option('command'))
            );
        });
    }

    private function runMigrations(): int
    {
        $failed = false;
        $migrator = app('migrator');
        $disabledForeignKeyConnections = [];

        /** @var SplFileInfo $fileInfo */
        foreach ($this->migrationFiles as $fileInfo) {
            if ($failed) {
                break;
            }

            try {
                /** @var Migration $migration */
                $migration = Sudo::callMethod(app('migrator'), 'resolvePath', $fileInfo->getRealPath());

                $connection = $this->connectionFor($migration);

                if (! in_array($connection, $disabledForeignKeyConnections)) {
                    Schema::connection($connection)->disableForeignKeyConstraints();
                    $disabledForeignKeyConnections[] = $connection;
                }

                $migrator->usingConnection($connection, function () use ($fileInfo, $migrator) {
                    $this->prepareMigrationRepository($migrator, $fileInfo);

                    $this->isDroppingViews()
                        ? $migrator->rollback($fileInfo->getRealPath())
                        : $migrator->run($fileInfo->getRealPath());
                });
            } catch (\Throwable $e) {
                $failed = true;

                $this->error("Error while migrating: {$e->getMessage()}");

                $this->newLine();

                $this->info($e->getTraceAsString());
            } finally {
                foreach ($disabledForeignKeyConnections as $connection) {
                    Schema::connection($connection)->enableForeignKeyConstraints();
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
