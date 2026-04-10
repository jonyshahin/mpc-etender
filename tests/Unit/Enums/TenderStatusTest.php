<?php

use App\Enums\BidStatus;
use App\Enums\TenderStatus;
use App\Enums\TenderType;
use App\Enums\VendorStatus;

test('all tender statuses have correct values', function () {
    expect(TenderStatus::Draft->value)->toBe('draft');
    expect(TenderStatus::Published->value)->toBe('published');
    expect(TenderStatus::SubmissionClosed->value)->toBe('submission_closed');
    expect(TenderStatus::UnderEvaluation->value)->toBe('under_evaluation');
    expect(TenderStatus::Awarded->value)->toBe('awarded');
    expect(TenderStatus::Completed->value)->toBe('completed');
    expect(TenderStatus::Cancelled->value)->toBe('cancelled');
});

test('tender status has exactly 7 cases', function () {
    expect(TenderStatus::cases())->toHaveCount(7);
});

test('tender type has exactly 4 cases', function () {
    expect(TenderType::cases())->toHaveCount(4);
});

test('vendor status has exactly 6 cases', function () {
    expect(VendorStatus::cases())->toHaveCount(6);
});

test('bid status has exactly 8 cases', function () {
    expect(BidStatus::cases())->toHaveCount(8);
});

test('all enums are string backed', function () {
    foreach (TenderStatus::cases() as $case) {
        expect($case->value)->toBeString();
    }
    foreach (TenderType::cases() as $case) {
        expect($case->value)->toBeString();
    }
    foreach (VendorStatus::cases() as $case) {
        expect($case->value)->toBeString();
    }
    foreach (BidStatus::cases() as $case) {
        expect($case->value)->toBeString();
    }
});
