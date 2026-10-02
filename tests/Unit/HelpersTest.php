<?php

it('formatea pesos colombianos', function () {
    expect(cop(1250000))->toBe('$ 1.250.000')
        ->and(cop(0))->toBe('$ 0')
        ->and(cop(-2000))->toBe('-$ 2.000');
});

it('formatea porcentajes con coma decimal', function () {
    expect(pct(33.3333))->toBe('33,3 %')->and(pct(null))->toBe('—');
});
