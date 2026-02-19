<?php

namespace Hemant\Pagewire\Traits;

use Illuminate\Support\Facades\Gate;

trait WithPermissions
{
    public function can(string $ability, mixed $arguments = []): bool
    {
        // If gate/policy not defined, default to allow so package works out-of-the-box.
        return Gate::has($ability) ? Gate::allows($ability, $arguments) : true;
    }
}
