<?php

function randomVariantId(): string
{
    $variants = ['A', 'B', 'C'];
    return $variants[array_rand($variants)];
}

function getVariant(): string
{
    return randomVariantId();
}

function createSessionId(): string
{
    return bin2hex(random_bytes(32));
}