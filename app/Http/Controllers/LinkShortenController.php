<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidLinkException;
use App\Http\Requests\Member\StoreLinkRequest;
use App\Services\LinkShortener;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class LinkShortenController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly LinkShortener $shortener) {}

    public function __invoke(StoreLinkRequest $request): JsonResponse
    {
        $level = $request->filled('ad_type') ? $request->integer('ad_type') : null;

        try {
            $link = $this->shortener->create(
                $request->user(),
                (string) $request->input('url'),
                $request->input('alias'),
                $level,
            );
        } catch (InvalidLinkException $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->success([
            'short_url' => url($link->alias),
            'alias' => $link->alias,
        ], 'Your link has been shortened.');
    }
}
