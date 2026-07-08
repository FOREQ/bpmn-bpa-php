<?php

function questionOrder(array $variant): array
{
    return array_map(function ($question) {
        return $question['id'];
    }, $variant);
}

function optionOrder(array $questions): array
{
    $result = [];

    foreach ($questions as $question) {
        $result[$question['id']] = array_keys($question['options']);
    }

    return $result;
}

function publicQuestions(array $variant, array $questionIds, array $orders): array
{
    $result = [];

    foreach ($questionIds as $questionId) {
        $question = null;

        foreach ($variant as $q) {
            if ((int)$q['id'] === (int)$questionId) {
                $question = $q;
                break;
            }
        }

        if (!$question) {
            continue;
        }

        $optionIndexes = $orders[$question['id']] ?? array_keys($question['options']);
        $orderedOptions = [];

        foreach ($optionIndexes as $index) {
            if (!isset($question['options'][$index])) {
                continue;
            }

            $option = $question['options'][$index];

            if (is_array($option)) {
                $orderedOptions[] = [
                    'id' => $index,
                    'text' => $option['text'] ?? '',
                    'image' => $option['image'] ?? null
                ];
            } else {
                $orderedOptions[] = [
                    'id' => $index,
                    'text' => $option,
                    'image' => null
                ];
            }
        }

        $result[] = [
            'id' => $question['id'],
            'number' => $question['id'],
            'text' => $question['question'],
            'image' => $question['image'] ?? null,
            'options' => $orderedOptions
        ];
    }

    return $result;
}

function gradeAnswers(array $variant, array $answers): array
{
    $total = count($variant);
    $score = 0;

    foreach ($variant as $question) {
        $questionId = (string)$question['id'];

        if (!array_key_exists($questionId, $answers)) {
            continue;
        }

        $selected = (int)$answers[$questionId];
        $correct = (int)$question['correct'];

        if ($selected === $correct) {
            $score++;
        }
    }

    $percent = $total > 0 ? round(($score / $total) * 100, 1) : 0;

    return [
        'score' => $score,
        'total' => $total,
        'percent' => $percent,
        'status' => $percent >= 70 ? 'passed' : 'failed'
    ];
}