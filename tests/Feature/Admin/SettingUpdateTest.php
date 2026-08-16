<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * system_settings.value is `text` NOT NULL, but ConvertEmptyStringsToNull turns
 * a cleared form field into null and UpdateSettingsRequest validates the value
 * as `nullable` — so a blank setting reached the UPDATE as null and threw.
 *
 * That became reachable on the very first save once general.website_url shipped
 * with a blank default, since it renders as an empty input.
 */
function adminForSettings(): User
{
    $role = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);
    $permission = Permission::create([
        'name' => 'Admin Settings',
        'slug' => 'admin.settings',
        'module' => 'admin',
    ]);
    $role->permissions()->attach($permission->id);

    return User::factory()->create(['role_id' => $role->id]);
}

function makeSetting(string $key, string $value = 'initial'): SystemSetting
{
    return SystemSetting::create([
        'key' => $key,
        'value' => $value,
        'group' => 'general',
        'type' => 'string',
        'description' => 'Test setting',
    ]);
}

test('a blank setting value is stored as an empty string, not NULL', function () {
    $admin = adminForSettings();
    makeSetting('general.website_url', 'https://old.example');

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'settings' => [
                ['key' => 'general.website_url', 'value' => ''],
            ],
        ])
        ->assertSessionHasNoErrors();

    $stored = SystemSetting::where('key', 'general.website_url')->value('value');

    expect($stored)->toBe('')->not->toBeNull();
});

test('clearing one setting does not abort the rest of the batch', function () {
    $admin = adminForSettings();
    makeSetting('general.company_name', 'MPC Group');
    makeSetting('general.website_url', 'https://old.example');
    makeSetting('general.default_currency', 'USD');

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'settings' => [
                ['key' => 'general.company_name', 'value' => 'MPC Group Iraq'],
                ['key' => 'general.website_url', 'value' => ''],
                ['key' => 'general.default_currency', 'value' => 'IQD'],
            ],
        ])
        ->assertSessionHasNoErrors();

    // The blank value used to throw mid-loop, committing the settings before it
    // and silently dropping the ones after.
    expect(SystemSetting::where('key', 'general.company_name')->value('value'))->toBe('MPC Group Iraq');
    expect(SystemSetting::where('key', 'general.website_url')->value('value'))->toBe('');
    expect(SystemSetting::where('key', 'general.default_currency')->value('value'))->toBe('IQD');
});

test('ordinary setting updates still work', function () {
    $admin = adminForSettings();
    makeSetting('general.company_name', 'MPC Group');

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'settings' => [
                ['key' => 'general.company_name', 'value' => 'MPC Group Iraq'],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(SystemSetting::where('key', 'general.company_name')->value('value'))->toBe('MPC Group Iraq');
});
