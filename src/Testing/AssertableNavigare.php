<?php

namespace Navigare\Testing;

use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\AssertionFailedError;

class AssertableNavigare extends AssertableJson
{
  /** @var string */
  private $component;

  /** @var string */
  private $url;

  /** @var string|null */
  private $version;

  public static function fromTestResponse(TestResponse $response): self
  {
    try {
      $response->assertViewHas('page');
      $page = json_decode(json_encode($response->viewData('page')), true);

      PHPUnit::assertIsArray($page);
      PHPUnit::assertArrayHasKey('component', $page);
      PHPUnit::assertArrayHasKey('props', $page);
      PHPUnit::assertArrayHasKey('url', $page);
      PHPUnit::assertArrayHasKey('version', $page);
    } catch (AssertionFailedError $e) {
      PHPUnit::fail('Not a valid Navigare response.');
    }

    $instance = static::fromArray($page['props']);
    $instance->component = $page['component'];
    $instance->url = $page['url'];
    $instance->version = $page['version'];

    return $instance;
  }

  public function component(string $value = null, $shouldExist = null): self
  {
    PHPUnit::assertSame(
      $value,
      $this->component,
      'Unexpected Navigare page component.'
    );

    if (
      $shouldExist ||
      (is_null($shouldExist) &&
        config('navigare.testing.ensure_components_exist', true))
    ) {
      try {
        app('navigare.components.finder')->find($value);
      } catch (InvalidArgumentException $exception) {
        PHPUnit::fail(
          sprintf('Navigare page component file [%s] does not exist.', $value)
        );
      }
    }

    return $this;
  }

  public function url(string $value): self
  {
    PHPUnit::assertSame($value, $this->url, 'Unexpected Navigare page url.');

    return $this;
  }

  public function version(string $value): self
  {
    PHPUnit::assertSame(
      $value,
      $this->version,
      'Unexpected Navigare asset version.'
    );

    return $this;
  }

  public function toArray()
  {
    return [
      'component' => $this->component,
      'props' => $this->prop(),
      'url' => $this->url,
      'version' => $this->version,
    ];
  }
}
