<?php
namespace ModelVersioning\Traits;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Versionable
{
    public static function bootVersionable()
    {
        static::updated(function (Model $model) {
            $model->storeVersion('update');
        });

        static::created(function (Model $model) {
            if (property_exists($model, 'versionOnCreate') && $model->versionOnCreate) {
                $model->storeVersion('create');
            }
        });

        static::deleted(function (Model $model) {
            if (!method_exists($model, 'forceDelete') || !$model->forceDeleting) {
                $model->storeVersion('delete');
            }
        });
    }

    protected function storeVersion($action = 'update')
    {
        if (!$this->versionTableExists()) {
            $this->createVersionsTable();
        }

        $this->getVersionConnection()->table($this->getVersionsTable())->insert([
            'original_id' => $this->getKey(),
            'version_number' => $this->getNextVersionNumber(),
            'data' => json_encode($this->getVersionableAttributes()),
            'action' => $action,
            'metadata' => json_encode($this->getVersionMetadata()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Specify the version table name
    protected function getVersionsTable()
    {
        return $this->getTable() . '_versions';
    }

    // Connection for versioning database
    protected function getVersionConnection()
    {
        return app('db')->connection('versioning');
    }

    protected function versionTableExists()
    {
        return $this->getVersionConnection()->getSchemaBuilder()->hasTable($this->getVersionsTable());
    }

    protected function createVersionsTable()
    {
        $this->getVersionConnection()->getSchemaBuilder()->create($this->getVersionsTable(), function (Blueprint $table) {
            $table->id('version_id');
            $table->foreignId('original_id')->constrained($this->getTable())->onDelete('cascade');
            $table->integer('version_number');
            $table->string('action');
            $table->json('data');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function getVersionableAttributes()
    {
        return $this->only($this->versionable ?? $this->getFillable());
    }

    protected function getNextVersionNumber()
    {
        $lastVersion = $this->getVersionConnection()->table($this->getVersionsTable())
            ->where('original_id', $this->getKey())
            ->max('version_number');

        return ($lastVersion ?? 0) + 1;
    }

    protected function getVersionMetadata()
    {
        return [
            'user_id' => Auth::id(),
            'ip_address' => request()->ip(),
        ];
    }

    public function versions()
    {
        return $this->getVersionConnection()->table($this->getVersionsTable())
            ->where('original_id', $this->getKey())
            ->orderBy('version_number', 'desc')->get();
    }

    public function restoreVersion($versionId, $asNew = false)
    {
        $version = $this->getVersionConnection()->table($this->getVersionsTable())
            ->where('version_id', $versionId)->first();

        if ($version) {
            $data = json_decode($version->data, true);
            if ($asNew) {
                return self::create($data);
            } else {
                $this->fill($data)->save();
            }
        }
    }
}
