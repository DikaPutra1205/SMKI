<?php

namespace App\Routing;

readonly class PageResolution
{
    public function __construct(
        public bool $allowed,
        public ?string $component = null,
        public array $requiredPermissions = [],
        public ?string $redirectTo = null,
    ) {}
}
