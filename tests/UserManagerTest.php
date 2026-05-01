<?php

namespace App\Tests;

use App\Entity\Users;
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    private UserManager $manager;

    protected function setUp(): void
    {
        $this->manager = new UserManager();
    }

    // ✅ Test 1 : User valide complet
    public function testUserValide(): void
    {
        $user = new Users();
        $user->setNom('Ben Ali');
        $user->setPrenom('Mohamed');
        $user->setEmail('mohamed@example.com');
        $user->setTelephone('12345678');

        $this->assertTrue($this->manager->validate($user));
    }

    // ✅ Test 2 : Nom vide → exception
    public function testNomObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire.');

        $user = new Users();
        $user->setNom('');
        $user->setPrenom('Mohamed');
        $user->setEmail('mohamed@example.com');

        $this->manager->validate($user);
    }

    // ✅ Test 3 : Prénom vide → exception
    public function testPrenomObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prénom est obligatoire.');

        $user = new Users();
        $user->setNom('Ben Ali');
        $user->setPrenom('');
        $user->setEmail('mohamed@example.com');

        $this->manager->validate($user);
    }

    // ✅ Test 4 : Email vide → exception
    public function testEmailObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'email est obligatoire.');

        $user = new Users();
        $user->setNom('Ben Ali');
        $user->setPrenom('Mohamed');
        $user->setEmail('');

        $this->manager->validate($user);
    }

    // ✅ Test 5 : Email invalide → exception
    public function testEmailInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'email n\'est pas valide.');

        $user = new Users();
        $user->setNom('Ben Ali');
        $user->setPrenom('Mohamed');
        $user->setEmail('email-invalide');

        $this->manager->validate($user);
    }

    // ✅ Test 6 : Nom trop court → exception
    public function testNomTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom doit contenir au moins 2 caractères.');

        $user = new Users();
        $user->setNom('A');
        $user->setPrenom('Mohamed');
        $user->setEmail('mohamed@example.com');

        $this->manager->validate($user);
    }

    // ✅ Test 7 : Prénom trop court → exception
    public function testPrenomTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prénom doit contenir au moins 2 caractères.');

        $user = new Users();
        $user->setNom('Ben Ali');
        $user->setPrenom('M');
        $user->setEmail('mohamed@example.com');

        $this->manager->validate($user);
    }

    // ✅ Test 8 : Téléphone invalide → exception
    public function testTelephoneInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le téléphone doit contenir exactement 8 chiffres.');

        $user = new Users();
        $user->setNom('Ben Ali');
        $user->setPrenom('Mohamed');
        $user->setEmail('mohamed@example.com');
        $user->setTelephone('123');

        $this->manager->validate($user);
    }

    // ✅ Test 9 : Téléphone null accepté
    public function testTelephoneNullAccepte(): void
    {
        $user = new Users();
        $user->setNom('Ben Ali');
        $user->setPrenom('Mohamed');
        $user->setEmail('mohamed@example.com');
        $user->setTelephone(null);

        $this->assertTrue($this->manager->validate($user));
    }

    // ✅ Test 10 : Téléphone avec lettres → exception
    public function testTelephoneAvecLettres(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le téléphone doit contenir exactement 8 chiffres.');

        $user = new Users();
        $user->setNom('Ben Ali');
        $user->setPrenom('Mohamed');
        $user->setEmail('mohamed@example.com');
        $user->setTelephone('1234abcd');

        $this->manager->validate($user);
    }
}