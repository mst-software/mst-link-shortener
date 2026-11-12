<?php

namespace App\Services\Views;

use App\Models\View;
use App\Services\Visits\CodeResult;

final readonly class FinishResult
{
    public function __construct(
        public ?View $view = null,
        public ?StepResult $error = null,
        public ?CodeResult $codeError = null,
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

    /**
     * The typed code was refused; no view is recorded and the code may have been counted as a failure.
     */
    public static function codeRejected(CodeResult $codeError): self
    {
        return new self(codeError: $codeError);
    }

    public function isFinished(): bool
    {
        return $this->view !== null;
    }
}
