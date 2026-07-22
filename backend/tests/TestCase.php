<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Safety net: RefreshDatabase runs migrate:fresh on the default
        // connection. If someone overrides DB_CONNECTION=mysql to run the
        // Stream suite and accidentally includes a RefreshDatabase test,
        // it would wipe the real editorial database (540 CRS, corpus, plans).
        // Refuse to run before the app boots and any migration executes.
        $usesRefresh = in_array(
            RefreshDatabase::class,
            class_uses_recursive(static::class),
            true,
        );

        if ($usesRefresh && (getenv('DB_CONNECTION') ?: 'sqlite') !== 'sqlite') {
            throw new RuntimeException(
                'RefreshDatabase tests must run on sqlite (:memory:). '
                . 'Refusing to migrate:fresh a real database.'
            );
        }

        parent::setUp();
    }
}
