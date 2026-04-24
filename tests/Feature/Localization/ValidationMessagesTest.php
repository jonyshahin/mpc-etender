<?php

use Illuminate\Support\Facades\Validator;

afterEach(function () {
    app()->setLocale(config('app.locale'));
});

// ── English (sanity — no regression) ──────────────────────────

it('returns English required message in English locale', function () {
    app()->setLocale('en');
    $v = Validator::make(['name' => ''], ['name' => 'required']);
    expect($v->errors()->first('name'))->toBe('The name field is required.');
});

it('resolves snake_case attributes through the attributes array in English', function () {
    app()->setLocale('en');
    $v = Validator::make(
        ['submission_deadline' => now()->addDays(5), 'opening_date' => now()->addDay()],
        ['opening_date' => 'required|date|after:submission_deadline']
    );
    expect($v->fails())->toBeTrue();

    $msg = $v->errors()->first('opening_date');
    expect($msg)->toContain('opening date');
    expect($msg)->toContain('submission deadline');
    expect($msg)->not->toContain('opening_date');
    expect($msg)->not->toContain('submission_deadline');
});

// ── Arabic (the new hotness) ──────────────────────────────────

it('returns Arabic required message in Arabic locale', function () {
    app()->setLocale('ar');
    $v = Validator::make(['name' => ''], ['name' => 'required']);
    expect($v->errors()->first('name'))->toContain('مطلوب');
});

it('returns Arabic email message in Arabic locale', function () {
    app()->setLocale('ar');
    $v = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
    expect($v->errors()->first('email'))->toContain('بريدًا إلكترونيًا');
});

it('returns Arabic date-after message with translated attribute names', function () {
    app()->setLocale('ar');
    $v = Validator::make(
        ['submission_deadline' => now()->addDays(5), 'opening_date' => now()->addDay()],
        ['opening_date' => 'required|date|after:submission_deadline']
    );
    expect($v->fails())->toBeTrue();
    $msg = $v->errors()->first('opening_date');

    // Rule text in Arabic
    expect($msg)->toContain('يجب أن يكون');
    expect($msg)->toContain('تاريخ الفتح');
    expect($msg)->toContain('الموعد النهائي للتقديم');

    // No snake_case leak
    expect($msg)->not->toContain('opening_date');
    expect($msg)->not->toContain('submission_deadline');

    // No English fallback
    expect($msg)->not->toContain('field must be');
});

it('returns Arabic min message with string-rule variant', function () {
    app()->setLocale('ar');
    $v = Validator::make(['password' => 'abc'], ['password' => 'string|min:8']);
    expect($v->errors()->first('password'))->toContain('8 حرف على الأقل');
});

it('returns Arabic confirmed message for password_confirmation', function () {
    app()->setLocale('ar');
    $v = Validator::make(
        ['password' => 'secret1', 'password_confirmation' => 'secret2'],
        ['password' => 'confirmed']
    );
    expect($v->errors()->first('password'))->toContain('غير مطابق');
});
