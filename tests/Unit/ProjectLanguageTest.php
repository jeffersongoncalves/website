<?php

declare(strict_types=1);

use App\Enums\ProjectLanguage;

it('maps a known GitHub language name onto a case', function (): void {
    expect(ProjectLanguage::tryFrom('PHP'))->toBe(ProjectLanguage::Php);
    expect(ProjectLanguage::tryFrom('Jupyter Notebook'))->toBe(ProjectLanguage::JupyterNotebook);
});

it('returns null for an unknown language', function (): void {
    expect(ProjectLanguage::tryFrom('Brainfuck'))->toBeNull();
    expect(ProjectLanguage::tryFrom(''))->toBeNull();
});

it('labels each case with its verbatim GitHub name', function (): void {
    expect(ProjectLanguage::Php->getLabel())->toBe('PHP');
    expect(ProjectLanguage::CSharp->getLabel())->toBe('C#');
});

it('returns a color for every case', function (): void {
    foreach (ProjectLanguage::cases() as $case) {
        expect($case->getColor())->toBeIn(['gray', 'primary', 'success', 'warning', 'danger', 'info']);
    }
});
