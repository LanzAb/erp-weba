<?php

/**
 * The shared test bootstrap runs migrate:fresh, so tests must never point at
 * the development database.
 */
it('runs against a dedicated test database', function () {
    expect(getenv('DB_DATABASE'))->toBe('weba_erp_test');
});
