<?php

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\StoreSepayTopUpRequest;
use App\Services\Advertiser\TopUpService;
use Illuminate\Http\RedirectResponse;

class TopUpController extends Controller
{
    public function __construct(private readonly TopUpService $topUps) {}

    public function sepay(StoreSepayTopUpRequest $request): RedirectResponse
    {
        $this->topUps->createSepay($request->user(), $request->integer('vnd'));

        return redirect()->route('advertiser.wallet')
            ->with('success', 'Transfer the amount below using the exact transfer note. Your balance is updated within a few minutes.');
    }
}
