<?php
namespace App\Tests\Service;

use App\Entity\Reclamation;
use App\Service\ReclamationManager;
use PHPUnit\Framework\TestCase;

class ReclamationManagerTest extends TestCase
{
    private function makeReclamation(): Reclamation
    {
        $r = new Reclamation();
        $r->setTitre('Problème de réservation hôtel');
        $r->setContenu('Ma chambre était sale et non conforme aux photos du site.');
        $r->setPriorite('Haute');
        $r->setStatut('En attente');
        $r->setDateCreation(new \DateTime('-1 day'));
        $r->setUserId(1);
        return $r;
    }

    // ✅ TEST 1 : Réclamation valide complète
    public function testReclamationValide(): void
    {
        $manager = new ReclamationManager();
        $this->assertTrue($manager->validate($this->makeReclamation()));
    }

    // ❌ TEST 2 : Titre obligatoire
    public function testTitreObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire.');
        $r = $this->makeReclamation();
        $r->setTitre('');
        (new ReclamationManager())->validerTitre($r);
    }

    // ❌ TEST 3 : Titre trop court
    public function testTitreTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 5 caractères.');
        $r = $this->makeReclamation();
        $r->setTitre('Bug');
        (new ReclamationManager())->validerTitre($r);
    }

    // ❌ TEST 4 : Titre trop long
    public function testTitreTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre ne peut pas dépasser 255 caractères.');
        $r = $this->makeReclamation();
        $r->setTitre(str_repeat('a', 256));
        (new ReclamationManager())->validerTitre($r);
    }

    // ❌ TEST 5 : Contenu obligatoire
    public function testContenuObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu est obligatoire.');
        $r = $this->makeReclamation();
        $r->setContenu('');
        (new ReclamationManager())->validerContenu($r);
    }

    // ❌ TEST 6 : Contenu trop court
    public function testContenuTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu doit contenir au moins 20 caractères.');
        $r = $this->makeReclamation();
        $r->setContenu('Trop court');
        (new ReclamationManager())->validerContenu($r);
    }

    // ❌ TEST 7 : Priorité invalide
    public function testPrioriteInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La priorité doit être : Basse, Moyenne, Haute ou Urgente.');
        $r = $this->makeReclamation();
        $r->setPriorite('Critique');
        (new ReclamationManager())->validerPriorite($r);
    }

    // ✅ TEST 8 : Priorité urgente valide
    public function testPrioriteUrgenteValide(): void
    {
        $r = $this->makeReclamation();
        $r->setPriorite('Urgente');
        $this->assertTrue((new ReclamationManager())->validerPriorite($r));
    }

    // ❌ TEST 9 : Statut invalide
    public function testStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut est invalide.');
        $r = $this->makeReclamation();
        $r->setStatut('Fermée');
        (new ReclamationManager())->validerStatut($r);
    }

    // ✅ TEST 10 : Statut résolu valide
    public function testStatutResoluValide(): void
    {
        $r = $this->makeReclamation();
        $r->setStatut('Résolue');
        $this->assertTrue((new ReclamationManager())->validerStatut($r));
    }

    // ❌ TEST 11 : Date dans le futur
    public function testDateDansLeFutur(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de création ne peut pas être dans le futur.');
        $r = $this->makeReclamation();
        $r->setDateCreation(new \DateTime('+1 day'));
        (new ReclamationManager())->validerDateCreation($r);
    }

    // ✅ TEST 12 : Date aujourd'hui valide
    public function testDateAujourdhuiValide(): void
    {
        $r = $this->makeReclamation();
        $r->setDateCreation(new \DateTime());
        $this->assertTrue((new ReclamationManager())->validerDateCreation($r));
    }

    // ❌ TEST 13 : UserId invalide (zéro)
    public function testUserIdZeroInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'identifiant utilisateur est invalide.');
        $r = $this->makeReclamation();
        $r->setUserId(0);
        (new ReclamationManager())->validerUserId($r);
    }

    // ✅ TEST 14 : UserId valide
    public function testUserIdValide(): void
    {
        $r = $this->makeReclamation();
        $r->setUserId(5);
        $this->assertTrue((new ReclamationManager())->validerUserId($r));
    }
}
