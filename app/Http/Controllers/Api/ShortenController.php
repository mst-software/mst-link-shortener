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
                $request->query('api'),
                $request->query('url'),
                $request->query('alias'),
                $request->filled('level') ? (int) $request->query('level') : null,
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
}
