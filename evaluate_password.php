<?php
// Shared password strength checker for registration + password reset

function evaluatePasswordStrength(string $pwd): array
{
    $score = 0;
    $tips  = [];

    // length check
    if (strlen($pwd) >= 8) {
        $score++;
    } else {
        $tips[] = "Use at least 8 characters.";
    }

    // uppercase
    if (preg_match('/[A-Z]/', $pwd)) {
        $score++;
    } else {
        $tips[] = "Add at least one uppercase letter.";
    }

    // lowercase
    if (preg_match('/[a-z]/', $pwd)) {
        $score++;
    } else {
        $tips[] = "Add at least one lowercase letter.";
    }

    // number
    if (preg_match('/[0-9]/', $pwd)) {
        $score++;
    } else {
        $tips[] = "Add at least one number.";
    }

    // special char
    if (preg_match('/[^A-Za-z0-9]/', $pwd)) {
        $score++;
    } else {
        $tips[] = "Add at least one special character ( !, ?, #, @).";
    }

    // label based on score
    if ($score <= 2) {
        $label = "Weak";
    } elseif ($score <= 4) {
        $label = "Medium";
    } else {
        $label = "Strong";
    }

    return [
        'score'        => $score,
        'label'        => $label,
        'requirements' => $tips,
    ];
}
