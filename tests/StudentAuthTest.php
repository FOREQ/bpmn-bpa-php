<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/student_auth.php';

final class StudentAuthTest extends TestCase
{
    private function participant(array $overrides = []): array
    {
        return array_merge([
            'passwordHash' => password_hash('random-technical-stub', PASSWORD_DEFAULT),
            'tempPasswordHash' => null,
            'tempPasswordExpiresAt' => null,
        ], $overrides);
    }

    public function testNewPasswordWorksAfterReset(): void
    {
        // Так выглядит запись после api/set_new_password.php:
        // passwordHash = новый пароль, временный пароль очищен.
        $participant = $this->participant([
            'passwordHash' => password_hash('NewPass1!', PASSWORD_DEFAULT),
        ]);

        $this->assertSame('permanent', verifyStudentPassword($participant, 'NewPass1!'));
        $this->assertSame('invalid', verifyStudentPassword($participant, 'WrongPass1!'));
    }

    public function testValidTemporaryPasswordWorks(): void
    {
        $now = strtotime('2026-11-23 10:00:00');
        $participant = $this->participant([
            'tempPasswordHash' => password_hash('Temp12345', PASSWORD_DEFAULT),
            'tempPasswordExpiresAt' => '2026-11-26 10:00:00',
        ]);

        $this->assertSame('temp', verifyStudentPassword($participant, 'Temp12345', $now));
        $this->assertSame('invalid', verifyStudentPassword($participant, 'Wrong', $now));
    }

    public function testExpiredTemporaryPasswordIsRejected(): void
    {
        $now = strtotime('2026-11-27 10:00:00');
        $participant = $this->participant([
            'tempPasswordHash' => password_hash('Temp12345', PASSWORD_DEFAULT),
            'tempPasswordExpiresAt' => '2026-11-26 10:00:00',
        ]);

        $this->assertSame('temp_expired', verifyStudentPassword($participant, 'Temp12345', $now));
    }

    public function testTechnicalStubFromRegistrationDoesNotLetAnyoneIn(): void
    {
        $participant = $this->participant();

        $this->assertSame('invalid', verifyStudentPassword($participant, ''));
        $this->assertSame('invalid', verifyStudentPassword($participant, 'anything'));
    }

    public function testPermanentPasswordStillWorksWhenTemporaryExpired(): void
    {
        $now = strtotime('2026-11-27 10:00:00');
        $participant = $this->participant([
            'passwordHash' => password_hash('NewPass1!', PASSWORD_DEFAULT),
            'tempPasswordHash' => password_hash('Temp12345', PASSWORD_DEFAULT),
            'tempPasswordExpiresAt' => '2026-11-26 10:00:00',
        ]);

        $this->assertSame('permanent', verifyStudentPassword($participant, 'NewPass1!', $now));
    }
}
