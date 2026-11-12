<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidLinkException;
use App\Http\Controllers\Controller;
use App\Services\TokenShortener;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShortenController extends Controller
{
    public function __construct(private readonly TokenShortener $tokenShortener) {}

    public function __invoke(Request $request): JsonResponse|Response
    {
        $isText = $request->query('format') === 'text';

        try {
            $link = $this->tokenShortener->shorten(
                $this->stringInput($request, 'api'),
                $this->stringInput($request, 'url'),
                $this->stringInput($request, 'alias'),
                $request->integer('level') ?: null,
            );
        } catch (InvalidLinkException $exception) {
            return $this->respond($isText ? response($exception->getMessage()) : response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]));
        }

        $shortUrl = url($link->alias);

        return $this->respond($isText ? response($shortUrl) : response()->json([
            'status' => 'success',
            'message' => '',
            'shortenedUrl' => $shortUrl,
        ]));
    }

    /**
     * The full page script calls this endpoint from other websites.
     */
    private function respond(JsonResponse|Response $response): JsonResponse|Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    /**
     * Read a query value only when it is a non-empty string (arrays become null).
     */
    private function stringInput(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
