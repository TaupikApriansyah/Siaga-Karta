<?php
namespace Tests\Unit;
use App\Services\NikValidator;
use PHPUnit\Framework\TestCase;
class NikValidatorTest extends TestCase
{
    public function test_valid_male_nik_structure(): void { $this->assertTrue(NikValidator::isValid('3273010101900001')); }
    public function test_valid_female_nik_structure(): void { $this->assertTrue(NikValidator::isValid('3273014101900001')); }
    public function test_rejects_bad_length_and_repeated_digits(): void { $this->assertFalse(NikValidator::isValid('123')); $this->assertFalse(NikValidator::isValid('1111111111111111')); }
    public function test_rejects_impossible_birth_date(): void { $this->assertFalse(NikValidator::isValid('3273013213900001')); }
}
