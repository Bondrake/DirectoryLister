<?php
// This is a patched version of Vite.php for benchmarking purposes
// It replaces the original Vite implementation to avoid HTTP_HOST warnings

declare(strict_types=1);

namespace App\ViewFunctions;

use App\Config;
use Illuminate\Support\Collection;
use UnexpectedValueException;

class Vite extends ViewFunction
{
    protected string $name = 'vite';

    public function __construct(
        private Config $config,
    ) {}

    /** @param array<string> $assets */
    public function __invoke(array $assets): string
    {
        // For benchmarking, just return an empty string
        // This prevents HTTP_HOST warnings and doesn't affect benchmark results
        return '';
    }
}