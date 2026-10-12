<?php

declare(strict_types=1);

use App\Support\Bidi;

it('isolates left-to-right values so RTL pages do not mirror them', function (): void {
    expect(Bidi::ltr('+974 5492 6330'))->toBe("\u{2066}+974 5492 6330\u{2069}");
});

it('leaves empty values untouched so placeholders still show', function (?string $value): void {
    expect(Bidi::ltr($value))->toBe($value);
})->with([null, '']);
