<?php

namespace App\Tests\Service;

use App\Service\UsersManager;
use App\Entity\Users;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class UsersManagerTest extends TestCase
{
    private UsersManager $usersManager;

    protected function setUp(): void
    {
        $this->usersManager = new UsersManager();
    }

    /**
     * Test : Utilisateur valide (toutes données correctes)
     */
    public function testValidateValidUser(): void
    {
        $user = $this->createValidUser();

        $this->assertTrue($this->usersManager->validate($user));
    }

    /**
     * Test : Nom vide → exception
     */
    public function testValidateEmptyNameThrowsException(): void
    {
        $user = $this->createValidUser();
        $user->setNom('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom ne peut pas être vide');

        $this->usersManager->validate($user);
    }

    /**
     * Test : Nom trop court (1 caractère) → exception
     */
    public function testValidateTooShortNameThrowsException(): void
    {
        $user = $this->createValidUser();
        $user->setNom('A');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom doit contenir au moins 2 caractères');

        $this->usersManager->validate($user);
    }

    /**
     * Test : Email invalide → exception
     */
    public function testValidateInvalidEmailThrowsException(): void
    {
        $user = $this->createValidUser();
        $user->setEmail('email-invalide');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le format de l\'email est invalide');

        $this->usersManager->validate($user);
    }

    /**
     * Test : Téléphone format incorrect → exception
     */
    public function testValidateInvalidTelephoneThrowsException(): void
    {
        $user = $this->createValidUser();
        $user->setTelephone('123456789');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de téléphone doit être au format +216XXXXXXXX');

        $this->usersManager->validate($user);
    }

    /**
     * Test : Utilisateur inactif (isActive=false) → ne peut pas réserver
     */
    public function testInactiveUserCannotReserveThrowsException(): void
    {
        $user = $this->createValidUser();
        $user->setIsActive(false); // Utilisateur inactif

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'utilisateur doit être actif pour faire une réservation');

        $this->usersManager->isEligibleForReservation($user);
    }

    /**
     * Test : Utilisateur non vérifié (isVerified=false) → ne peut pas réserver
     */
    public function testUnverifiedUserCannotReserveThrowsException(): void
    {
        $user = $this->createValidUser();
        $user->setIsVerified(false); // Utilisateur non vérifié

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'utilisateur doit être vérifié pour faire une réservation');

        $this->usersManager->isEligibleForReservation($user);
    }

    /**
     * Test : Utilisateur vérifié et actif → peut réserver
     */
    public function testActiveVerifiedUserCanReserve(): void
    {
        $user = $this->createValidUser();
        $user->setIsActive(true);   // Utilisateur actif
        $user->setIsVerified(true); // Utilisateur vérifié

        $this->assertTrue($this->usersManager->isEligibleForReservation($user));
    }

    /**
     * Test : validateEmail avec email valide
     */
    public function testValidateValidEmail(): void
    {
        $this->assertTrue($this->usersManager->validateEmail('test@example.com'));
    }

    /**
     * Test : validateEmail avec email vide
     */
    public function testValidateEmptyEmailThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'email ne peut pas être vide');

        $this->usersManager->validateEmail('');
    }

    /**
     * Test : validateEmail avec email trop long
     */
    public function testValidateTooLongEmailThrowsException(): void
    {
        $longEmail = str_repeat('a', 140) . '@example.com'; // 150+ caractères

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'email ne peut pas dépasser 150 caractères');

        $this->usersManager->validateEmail($longEmail);
    }

    /**
     * Test : validateTelephone avec numéro valide
     */
    public function testValidateValidTelephone(): void
    {
        $this->assertTrue($this->usersManager->validateTelephone('+21612345678'));
    }

    /**
     * Test : validateTelephone avec téléphone vide
     */
    public function testValidateEmptyTelephoneThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de téléphone ne peut pas être vide');

        $this->usersManager->validateTelephone('');
    }

    /**
     * Crée un utilisateur valide pour les tests
     */
    private function createValidUser(): Users
    {
        $user = new Users();
        $user->setNom('Dupont');
        $user->setPrenom('Jean');
        $user->setEmail('jean.dupont@example.com');
        $user->setTelephone('+21612345678');
        $user->setIsActive(true);
        $user->setIsVerified(true);

        return $user;
    }
}