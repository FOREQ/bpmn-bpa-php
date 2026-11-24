<?php

/** Случайный вариант теста: встроенные A, B, C и полностью одобренные сгенерированные (D, E…). */
function randomVariantId(?PDO $pdo = null): string
{
    $variants = ['A', 'B', 'C'];
    if ($pdo !== null) {
        require_once __DIR__ . '/exam_variants.php';
        $variants = examActiveVariantCodes($pdo);
    }
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