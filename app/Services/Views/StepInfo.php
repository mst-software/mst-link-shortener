<?php

namespace App\Services\Views;

final readonly class StepInfo
{
    public function __construct(
        public StepResult $result,
        public ?int $step = null,
        public ?int $totalSteps = null,
        public ?int $dwellSeconds = null,
        public ?string $token = null,
        public bool $captchaRequired = false,
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
