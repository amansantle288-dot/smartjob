<?php
declare(strict_types=1);

interface AIWritingServiceInterface
{
    public function generate(string $instructions, string $input): string;
}
