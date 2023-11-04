<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

trait HasAutoMigrations
{
    abstract protected function migration(): Migration;

    private static bool $booting = false;

    /** @var string[] */
    private static array $migrationsRan = [];

    /** @var class-string<Model>[] */
    private static array $autoMigrationDisabled = [];

    protected function initializeHasAutoMigrations(): void
    {
        if (static::$booting) {
            return;
        }

        if (in_array($this->getTable(), self::$autoMigrationDisabled)) {
            return;
        }

        if (in_array($this->getTable(), self::$migrationsRan)) {
            return;
        }

        if (! $this->shouldAutoMigrate()) {
            self::$migrationsRan = array_unique(array_merge(self::$migrationsRan, [$this->getTable()]));

            return;
        }

        static::$booting = true;

        $this->migrating($migration = $this->migration());

        try {
            $this->migrate($migration);
        } catch (QueryException $e) {
            if (str_starts_with($e->getMessage(), 'SQLSTATE[42S01]: Base table or view already exists')) {
                return;
            }

            throw $e;
        } finally {
            static::$booting = false;

            self::$migrationsRan = array_unique(array_merge(self::$migrationsRan, [$this->getTable()]));
        }
    }

    protected function migrate(Migration $migration): void
    {
        app('migrator')->usingConnection($migration->getConnection(), function () use ($migration) {
            $repo = app('migration.repository');

            if (! $repo->repositoryExists()) {
                $repo->createRepository();
            }

            $migrationName = basename((new \ReflectionClass($migration))->getFileName(), '.php');

            if ($this->shouldDeleteExistingMigrationLog()) {
                $this->deleteExistingMigrationLog($migrationName, DB::connection($migration->getConnection()));
            }

            /** @noinspection PhpVoidFunctionResultUsedInspection */
            $method = tap(new ReflectionMethod(app('migrator'), 'runMigration'))->setAccessible(true);

            $method->invoke($migrator = app('migrator'), $migration, 'up');

            if ($this->shouldCreateMigrationLog($migrator, $migrationName)) {
                $repo->log($migrationName, $repo->getNextBatchNumber());
            }
        });

        $this->migrated($migration);
    }

    protected function shouldCreateMigrationLog(Migrator $migrator, string $migrationName): bool
    {
        return ! in_array($migrationName, $migrator->getRepository()->getRan());
    }

    protected function shouldDeleteExistingMigrationLog(): bool
    {
        return true;
    }

    protected function shouldAutoMigrate(): bool
    {
        return true;
    }

    protected function migrating(Migration $migration): void
    {
        //
    }

    protected function migrated(Migration $migration): void
    {
        //
    }

    private function deleteExistingMigrationLog(string $migrationName, Connection $connection): void
    {
        $connection->table('migrations')->where('migration', $migrationName)->delete();
    }

    /**
     * Can be called from a ServiceProvider to disable to auto-migration
     */
    public static function disableAutoMigration(): void
    {
        self::$autoMigrationDisabled = array_unique(array_merge(self::$autoMigrationDisabled, [Model::withoutEvents(function () {
            return (new \ReflectionClass(static::class))->newInstanceWithoutConstructor()->getTable();
        })]));
    }
}
