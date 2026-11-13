<?php

namespace App\Http\Controllers\Advertiser;

use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\StorePaypalTopUpRequest;
use App\Http\Requests\Advertiser\StoreSepayTopUpRequest;
use App\Models\TopUp;
use App\Services\Advertiser\TopUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TopUpController extends Controller
{
    public function __construct(private readonly TopUpService $topUps) {}

    public function sepay(StoreSepayTopUpRequest $request): RedirectResponse
    {
        $this->topUps->createSepay($request->user(), $request->integer('vnd'));

        return redirect()->route('advertiser.wallet')
            ->with('success', 'Transfer the amount below using the exact transfer note. Your balance is updated within a few minutes.');
    }

    public function paypal(StorePaypalTopUpRequest $request): RedirectResponse
    {
        try {
            $order = $this->topUps->createPaypal($request->user(), $request->micro());
        } catch (RuntimeException) {
            return redirect()->route('advertiser.wallet')->with('error', 'PayPal is not available right now. Please try again later.');
        }

        return redirect()->away($order['approve_url']);
    }

    public function paypalReturn(Request $request): RedirectResponse
    {
        $topUp = $this->ownPaypalTopUp($request);

        try {
            $status = $this->topUps->capturePaypal($topUp);
        } catch (RuntimeException) {
            return redirect()->route('advertiser.wallet')->with('error', 'We could not confirm your PayPal payment yet. Please open this page again in a minute.');
        }

        return redirect()->route('advertiser.wallet')->with(
            $status === TopUpStatus::Completed ? 'success' : 'error',
            $status === TopUpStatus::Completed ? 'Payment received. Your balance has been updated.' : 'PayPal did not complete the payment, so nothing was added to your balance.',
        );
    }

    public function paypalCancel(Request $request): RedirectResponse
    {
        $this->topUps->cancel($this->ownPaypalTopUp($request));

        return redirect()->route('advertiser.wallet')->with('error', 'The PayPal payment was cancelled.');
    }

    /**
     * The PayPal top up named by the "token" query parameter, only when it belongs to the signed in user.
     */
    private function ownPaypalTopUp(Request $request): TopUp
    {
        return TopUp::query()
            ->where('provider', TopUpProvider::Paypal)
            ->where('reference', (string) $request->query('token'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
