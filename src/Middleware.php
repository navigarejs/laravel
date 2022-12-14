<?php

namespace Navigare;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Navigare\Response as NavigareResponse;
use Symfony\Component\HttpFoundation\Response;

class Middleware
{
  /**
   * The root template that's loaded on the first page visit.
   *
   * @var string
   */
  protected $rootView = 'app';

  /**
   * Sets the root template that's loaded on the first page visit.
   *
   * @param  Request  $request
   * @return string
   */
  public function rootView(Request $request)
  {
    return $this->getRootView($request);
  }

  /**
   * Sets the root template that's loaded on the first page visit.
   *
   * @param  Request  $request
   * @return string
   */
  public function getRootView(Request $request)
  {
    return $this->rootView;
  }

  /**
   * Determines the current asset version.
   *
   * @see https://navigarejs.github.io/framework/asset-versioning
   *
   * @param  \Illuminate\Http\Request  $request
   * @return string|null
   */
  public function version(Request $request)
  {
    return $this->getVersion($request);
  }

  /**
   * Determines the current asset version.
   *
   * @see https://navigarejs.github.io/framework/asset-versioning
   *
   * @param  \Illuminate\Http\Request  $request
   * @return string|null
   */
  public function getVersion(Request $request)
  {
    return Navigare::getConfiguration()->getVersion();
  }

  /**
   * Extend response, i.e. with shared fragments.
   *
   * @see https://navigarejs.github.io/framework/
   * @param  \Illuminate\Http\Request  $request
   * @param  \Navigare\Response  $response
   * @return array|void
   */
  public function share(Request $request, \Navigare\Response $response)
  {
    return [];
  }

  /**
   * Handle the incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  Closure  $next
   * @return Response
   */
  public function handle(Request $request, Closure $next)
  {
    // Set root view
    Navigare::setRootView($this->getRootView($request));

    // Set version
    Navigare::setVersion(function () use ($request) {
      return $this->getVersion($request);
    });

    // Expose validation errors by default
    Navigare::share(function (Request $request, NavigareResponse $response) {
      $response->with([
        '__errors' => function () use ($request) {
          return $this->resolveValidationErrors($request);
        },

        '__flash' => function () use ($request) {
          return $request->session()->get('navigare_flash') ?? [];
        },
      ]);
    });

    // Expose shared props
    Navigare::share(function (Request $request, NavigareResponse $response) {
      return $this->share($request, $response);
    });

    // Retrieve response by calling the middleware stack
    $response = $next($request);

    // Indicate that X-Navigare header can change
    $response->headers->set('Vary', 'X-Navigare');

    // In case no Navigare request was requested we simply return the response as is
    if (!$request->header('X-Navigare')) {
      return $response;
    }

    // In case the version differs between the requested one and the current one, we
    // trigger a redirect so the client loads all assets again
    if (
      $request->method() === 'GET' &&
      $request->header('X-Navigare-Version', '') !== Navigare::getVersion()
    ) {
      $response = $this->onVersionChange($request, $response);
    }

    // Empty responses usually indicate a back behaviour but can
    // be overriden by the middleware
    if ($response->isOk() && empty($response->getContent())) {
      $response = $this->onEmptyResponse($request, $response);
    }

    // Redirect via 303 code for requests with potential side effects
    if (
      $response->getStatusCode() === 302 &&
      in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])
    ) {
      $response->setStatusCode(303);
    }

    return $response;
  }

  /**
   * Determines what to do when an Navigare action returned with no response.
   * By default, we'll redirect the user back to where they came from.
   *
   * @param  Request  $request
   * @param  Response  $response
   * @return Response
   */
  public function onEmptyResponse(
    Request $request,
    Response $response
  ): Response {
    return Redirect::back();
  }

  /**
   * Determines what to do when the Navigare asset version has changed.
   * By default, we'll initiate a client-side location visit to force an update.
   *
   * @param  Request  $request
   * @param  Response  $response
   * @return Response
   */
  public function onVersionChange(
    Request $request,
    Response $response
  ): Response {
    if ($request->hasSession()) {
      $request->session()->reflash();
    }

    return Navigare::location($request->fullUrl());
  }

  /**
   * Resolves and prepares validation errors in such
   * a way that they are easier to use client-side.
   *
   * @param  Request  $request
   * @return object
   */
  public function resolveValidationErrors(Request $request)
  {
    if (!$request->hasSession() || !$request->session()->has('errors')) {
      return (object) [];
    }

    return (object) collect(
      $request
        ->session()
        ->get('errors')
        ->getBags()
    )
      ->map(function ($bag) {
        return (object) collect($bag->messages())
          ->map(function ($errors) {
            return $errors[0];
          })
          ->toArray();
      })
      ->pipe(function ($bags) use ($request) {
        if ($bags->has('default') && $request->header('X-Navigare-Error-Bag')) {
          return [
            $request->header('X-Navigare-Error-Bag') => $bags->get('default'),
          ];
        }

        if ($bags->has('default')) {
          return $bags->get('default');
        }

        return $bags->toArray();
      });
  }
}
