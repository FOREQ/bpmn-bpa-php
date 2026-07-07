<?php

function getDb(): PDO
{
    $dbPath = __DIR__ . '/../database/database.sqlite';

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}