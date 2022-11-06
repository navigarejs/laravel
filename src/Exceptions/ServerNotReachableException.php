<?php

namespace Navigare\Exceptions;

use Spatie\Ignition\Contracts\BaseSolution;
use Spatie\Ignition\Contracts\ProvidesSolution;
use Spatie\Ignition\Contracts\Solution;

final class ServerNotReachableException extends Exception implements
  ProvidesSolution
{
  protected array $links = [];

  public function __construct(
    protected string $endpoint,
    protected \Exception $exception
  ) {
    $this->message =
      'The Navigare server is not reachable at ' . $endpoint . '.';
  }

  public function getSolution(): Solution
  {
    return app()->environment('local')
      ? $this->getLocalSolution()
      : $this->getProductionSolution();
  }

  protected function getLocalSolution(): Solution
  {
    return BaseSolution::create('Start the development server')
      ->setSolutionDescription(
        "Run `{$this->getCommand(
          'vite'
        )}` in your terminal and refresh the page."
      )
      ->setDocumentationLinks($this->links);
  }

  protected function getProductionSolution(): Solution
  {
    return BaseSolution::create('Start the production server')
      ->setSolutionDescription(
        "Run `{$this->getCommand(
          'navigare'
        )}` in your terminal and refresh the page."
      )
      ->setDocumentationLinks($this->links);
  }
}
