<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    /**
     * The three instruction image fields.
     *
     * @var list<string>
     */
    public const IMAGE_FIELDS = ['image_google', 'image_button', 'image_position'];

    /**
     * Common two-label public suffixes: a campaign on one of these would match every site below it.
     *
     * @var list<string>
     */
    private const PUBLIC_SUFFIXES = [
        'co.uk', 'org.uk', 'me.uk', 'ac.uk', 'gov.uk', 'com.vn', 'net.vn', 'org.vn', 'edu.vn', 'gov.vn',
        'com.au', 'net.au', 'org.au', 'co.nz', 'co.jp', 'or.jp', 'ne.jp', 'co.kr', 'or.kr', 'co.in', 'net.in',
        'com.br', 'com.cn', 'com.hk', 'com.sg', 'com.my', 'com.tw', 'com.ph', 'com.mx', 'com.ar', 'com.tr',
        'co.id', 'co.th', 'in.th', 'co.za', 'github.io', 'herokuapp.com', 'blogspot.com', 'vercel.app', 'netlify.app',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reduce the domain to a bare lowercase host before validation.
     */
    protected function prepareForValidation(): void
    {
        $domain = $this->input('domain');

        if (is_string($domain)) {
            $host = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', trim($domain));
            $host = strtolower(explode('/', (string) $host)[0]);

            $this->merge(['domain' => $host]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'keyword' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'regex:/^(localhost|127\.0\.0\.1|(?!-)[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,})$/', $this->domainRule(...)],
            'display_domain' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'weight' => ['required', 'integer', 'min:1', 'max:1000'],
            'daily_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'stage1_seconds' => ['required', 'integer', 'min:5', 'max:600'],
            'stage2_seconds' => ['required', 'integer', 'min:5', 'max:600'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        foreach (self::IMAGE_FIELDS as $field) {
            $rules[$field] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:1024'];
            $rules['remove_'.$field] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * Reject public suffixes, and local hosts outside a local environment.
     */
    private function domainRule(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower((string) $value);

        if (in_array($domain, ['localhost', '127.0.0.1'], true)) {
            if (! app()->isLocal()) {
                $fail('The domain must be a real public domain.');
            }

            return;
        }

        if (in_array($domain, self::PUBLIC_SUFFIXES, true)) {
            $fail('The domain must be a specific site, not a public suffix such as '.$domain.'.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'display_domain' => 'display domain',
            'is_active' => 'active',
            'daily_limit' => 'daily limit',
            'starts_at' => 'start date',
            'ends_at' => 'end date',
            'stage1_seconds' => 'stage 1 seconds',
            'stage2_seconds' => 'stage 2 seconds',
            'image_google' => 'Google result image',
            'image_button' => 'GET CODE button image',
            'image_position' => 'position image',
        ];
    }
}
