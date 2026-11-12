<?php

namespace App\Services\Views;

use App\Enums\VisitMode;

final readonly class StepInfo
{
    public function __construct(
        public StepResult $result,
        public ?int $step = null,
        public ?int $totalSteps = null,
        public ?int $dwellSeconds = null,
        public ?string $token = null,
        public bool $captchaRequired = false,
        public VisitMode $mode = VisitMode::Wait,
        /** @var array<string, mixed>|null Keyword mode data for the visitor page. */
        public ?array $keyword = null,
    ) {}

    /**
     * A rejected step request: no step data and no token.
     */
    public static function rejected(StepResult $result): self
    {
        return new self($result);
    }

    public function accepted(): bool
    {
        return $this->result === StepResult::Accepted;
    }
}
