<?php

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audit log cannot be updated', function () {
    $log = AuditLog::forceCreate([
        'auditable_type' => 'test',
        'auditable_id' => 'test-id',
        'action' => 'created',
        'created_at' => now(),
    ]);

    $log->action = 'updated';
    $log->save();
})->throws(RuntimeException::class, 'Audit logs are append-only and cannot be updated.');

test('audit log cannot be deleted', function () {
    $log = AuditLog::forceCreate([
        'auditable_type' => 'test',
        'auditable_id' => 'test-id',
        'action' => 'created',
        'created_at' => now(),
    ]);

    $log->delete();
})->throws(RuntimeException::class, 'Audit logs are append-only and cannot be deleted.');

test('audit log can be created', function () {
    $log = AuditLog::forceCreate([
        'auditable_type' => 'test',
        'auditable_id' => 'test-id',
        'action' => 'created',
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    expect($log)->toBeInstanceOf(AuditLog::class);
    expect($log->exists)->toBeTrue();
});
