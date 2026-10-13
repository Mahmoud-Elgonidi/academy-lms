<?php

use Database\Seeders\E2eResetSeeder;

/*
 * The portal's end-to-end reset (apps/web/e2e/global-setup.ts) changes the
 * demo academy's demo accounts only: never in production, and nothing at
 * all where there is no demo academy.
 */

it('refuses to run in production', function () {
    $this->app['env'] = 'production';
    $this->createAcademy(['slug' => 'demo']);

    expect(fn () => app()->call([new E2eResetSeeder, 'run']))->toThrow(RuntimeException::class, 'never in production');
    expect(fn () => E2eResetSeeder::approveProof('TRF-1'))->toThrow(RuntimeException::class, 'never in production');
    expect(fn () => E2eResetSeeder::lessonNow('teacher'))->toThrow(RuntimeException::class, 'never in production');
});

it('touches nothing without the demo academy', function () {
    $this->createAcademy(['slug' => 'noor']);

    expect(fn () => app()->call([new E2eResetSeeder, 'run']))->toThrow(RuntimeException::class, 'No demo academy here');
    expect(fn () => E2eResetSeeder::lessonNow('student'))->toThrow(RuntimeException::class, 'No demo academy here');
});
