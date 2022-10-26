<?php

namespace Jaulz\Navigare\Tests;

use Illuminate\Foundation\Testing\TestResponse as LegacyTestResponse;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Jaulz\Navigare\Navigare;
use Jaulz\Navigare\ServiceProvider;
use LogicException;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
  protected function getPackageProviders($app): array
  {
    return [ServiceProvider::class];
  }

  protected function setUp(): void
  {
    parent::setUp();

    View::addLocation(__DIR__ . '/Stubs');

    Navigare::setRootView('welcome');
    config()->set('navigare.testing.ensure_pages_exist', false);
    config()->set('navigare.testing.page_paths', [realpath(__DIR__)]);
  }

  /**
   * @return string
   *
   * @throws LogicException
   */
  protected function getTestResponseClass(): string
  {
    // Laravel >= 7.0
    if (class_exists(TestResponse::class)) {
      return TestResponse::class;
    }

    // Laravel <= 6.0
    if (class_exists(LegacyTestResponse::class)) {
      return LegacyTestResponse::class;
    }

    throw new LogicException('Could not detect TestResponse class.');
  }

  /** @returns TestResponse|LegacyTestResponse */
  protected function makeMockRequest($view)
  {
    app('router')->get('/example-url', function () use ($view) {
      return $view;
    });

    return $this->get('/example-url');
  }

  /**
   * Does nothing.
   */
  public function noop()
  {
  }
}