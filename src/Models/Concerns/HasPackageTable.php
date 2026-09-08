<?php

namespace Easybdit\LaravelEasyAttendance\Models\Concerns;

/**
 * Resolves the model's table from config('attendance.table_names.<key>')
 * instead of Eloquent's default naming convention, so a host app can
 * rename any single package table (to dodge a collision, or to point the
 * package at a table it already has) without forking anything — see the
 * config file's "Table names" section. Every package model uses this;
 * $tableConfigKey is the only thing each one has to declare.
 */
trait HasPackageTable
{
    public function getTable()
    {
        return $this->table ??= config('attendance.table_names.'.$this->tableConfigKey());
    }
}
