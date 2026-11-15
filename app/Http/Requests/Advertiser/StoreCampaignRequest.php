<?php

namespace App\Http\Requests\Advertiser;

use App\Http\Requests\Admin\StoreCampaignRequest as AdminStoreCampaignRequest;
use App\Models\Campaign;
use Illuminate\Support\Arr;

/**
 * The campaign form of an advertiser: the admin rules without the fields only an admin may set
 * (weight, stage seconds, display domain, active flag).
 */
class StoreCampaignRequest extends AdminStoreCampaignRequest
{
    /**
     * Fields an advertiser fills in.
     *
     * @var list<string>
     */
    public const FIELDS = ['name', 'keyword', 'domain', 'daily_limit', 'starts_at', 'ends_at', 'notes'];

    /**
     * Another advertiser's campaign does not exist for this user.
     */
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        abort_if($campaign instanceof Campaign && $campaign->user_id !== $this->user()?->id, 404);

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $images = array_merge(
            self::IMAGE_FIELDS,
            array_map(fn (string $field): string => 'remove_'.$field, self::IMAGE_FIELDS),
        );

        return Arr::only(parent::rules(), [...self::FIELDS, ...$images]);
    }
}
