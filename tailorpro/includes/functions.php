<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return '₦' . number_format($amount, 2);
}

function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}
