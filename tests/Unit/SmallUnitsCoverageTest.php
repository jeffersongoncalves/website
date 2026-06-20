<?php

use App\Enums\PackageType;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

use function App\Support\enum_equals;

// ---------------------------------------------------------------------------
// PackageType enum
// ---------------------------------------------------------------------------

it('backs every PackageType case with its string value', function () {
    expect(PackageType::Composer->value)->toBe('composer')
        ->and(PackageType::Npm->value)->toBe('npm')
        ->and(PackageType::JetBrains->value)->toBe('jetbrains')
        ->and(PackageType::Docker->value)->toBe('docker')
        ->and(PackageType::None->value)->toBe('none');
});

it('returns the translated label for every PackageType case', function (PackageType $case, string $key) {
    expect($case->getLabel())->toBe(__($key));
})->with([
    'composer' => [PackageType::Composer, 'admin.enums.package_type.composer'],
    'npm' => [PackageType::Npm, 'admin.enums.package_type.npm'],
    'jetbrains' => [PackageType::JetBrains, 'admin.enums.package_type.jetbrains'],
    'docker' => [PackageType::Docker, 'admin.enums.package_type.docker'],
    'none' => [PackageType::None, 'admin.enums.package_type.none'],
]);

it('maps every PackageType case to its color', function (PackageType $case, string $color) {
    expect($case->getColor())->toBe($color);
})->with([
    'composer' => [PackageType::Composer, 'warning'],
    'npm' => [PackageType::Npm, 'danger'],
    'jetbrains' => [PackageType::JetBrains, 'info'],
    'docker' => [PackageType::Docker, 'info'],
    'none' => [PackageType::None, 'gray'],
]);

it('implements the Filament HasLabel and HasColor contracts', function () {
    expect(PackageType::Composer)->toBeInstanceOf(HasLabel::class)
        ->and(PackageType::Composer)->toBeInstanceOf(HasColor::class);
});

// ---------------------------------------------------------------------------
// enum_equals() helper
// ---------------------------------------------------------------------------

it('matches a raw scalar value against a single BackedEnum', function () {
    expect(enum_equals('npm', PackageType::Npm))->toBeTrue()
        ->and(enum_equals('composer', PackageType::Npm))->toBeFalse();
});

it('matches a BackedEnum value against a single BackedEnum', function () {
    expect(enum_equals(PackageType::Npm, PackageType::Npm))->toBeTrue()
        ->and(enum_equals(PackageType::Docker, PackageType::Npm))->toBeFalse();
});

it('returns false when a raw value does not resolve to the enum', function () {
    // 'bogus' is not a valid PackageType case, so tryFrom() yields null !== enum.
    expect(enum_equals('bogus', PackageType::Npm))->toBeFalse();
});

it('matches a BackedEnum value against a list of BackedEnums', function () {
    expect(enum_equals(PackageType::Npm, [PackageType::Composer, PackageType::Npm]))->toBeTrue()
        ->and(enum_equals(PackageType::Docker, [PackageType::Composer, PackageType::Npm]))->toBeFalse();
});

it('returns false for an empty enum list', function () {
    expect(enum_equals(PackageType::Npm, []))->toBeFalse();
});

it('matches a raw scalar value against a list of enums', function () {
    expect(enum_equals('npm', [PackageType::Composer, PackageType::Npm]))->toBeTrue()
        ->and(enum_equals('docker', [PackageType::Composer, PackageType::Npm]))->toBeFalse();
});
