<?php

namespace App\Services\Views;

use App\Models\View;

final readonly class FinishResult
{
    public function __construct(
        public ?View $view = null,
        public ?StepResult $error = null,
    ) {}

    /**
     * The visit is finished: the view is recorded (valid or not) and the destination may be shown.
     */
    public static function finished(View $view): self
    {
        return new self(view: $view);
    }

    /**
     * The request could not finish the visit; no view is recorded.
     */
    public static function rejected(StepResult $error): self
    {
        return new self(error: $error);
    }

    public function isFinished(): bool
    {
        return $this->view !== null;
    }
}
