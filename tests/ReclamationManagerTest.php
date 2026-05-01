<?php

namespace App\Tests;

use App\Entity\Reclamation;
use App\Service\ReclamationManager;
use PHPUnit\Framework\TestCase;

class ReclamationManagerTest extends TestCase
{
    private ReclamationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ReclamationManager();
    }

    // ✅ Test 1 : Réclamation valide
    public function testReclamationValide(): void
    {
        $reclamation = new Reclamation();
        $reclamation->setTitre('Problème avec ma réservation');
        $reclamation->setContenu('Je rencontre un problème avec ma réservation de voyage.');
        $reclamation->setPriorite('Haute');
        $reclamation->setStatut('en_attente');

        $this->assertTrue($this->manager->validate($reclamation));
    }

    // ✅ Test 2 : Titre vide → exception
    public function testTitreObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire.');

        $reclamation = new Reclamation();
        $reclamation->setTitre('');
        $reclamation->setContenu('Contenu valide de la réclamation.');

        $this->manager->validate($reclamation);
    }

    // ✅ Test 3 : Contenu vide → exception
    public function testContenuObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu est obligatoire.');

        $reclamation = new Reclamation();
        $reclamation->setTitre('Titre valide');
        $reclamation->setContenu('');

        $this->manager->validate($reclamation);
    }

    // ✅ Test 4 : Titre trop court → exception
    public function testTitreTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 5 caractères.');

        $reclamation = new Reclamation();
        $reclamation->setTitre('Bug');
        $reclamation->setContenu('Contenu valide de la réclamation.');

        $this->manager->validate($reclamation);
    }

    // ✅ Test 5 : Contenu trop court → exception
    public function testContenuTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu doit contenir au moins 10 caractères.');

        $reclamation = new Reclamation();
        $reclamation->setTitre('Titre valide');
        $reclamation->setContenu('Court');

        $this->manager->validate($reclamation);
    }

    // ✅ Test 6 : Priorité invalide → exception
    public function testPrioriteInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La priorité doit être : Basse, Moyenne, Haute ou Urgente.');

        $reclamation = new Reclamation();
        $reclamation->setTitre('Titre valide');
        $reclamation->setContenu('Contenu valide de la réclamation.');
        $reclamation->setPriorite('Critique');

        $this->manager->validate($reclamation);
    }

    // ✅ Test 7 : Statut invalide → exception
    public function testStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut doit être : en_attente, en_cours, resolue ou rejetee.');

        $reclamation = new Reclamation();
        $reclamation->setTitre('Titre valide');
        $reclamation->setContenu('Contenu valide de la réclamation.');
        $reclamation->setPriorite('Haute');
        $reclamation->setStatut('inconnu');

        $this->manager->validate($reclamation);
    }

    // ✅ Test 8 : Toutes les priorités valides
    public function testToutesPrioritesValides(): void
    {
        foreach (['Basse', 'Moyenne', 'Haute', 'Urgente'] as $priorite) {
            $reclamation = new Reclamation();
            $reclamation->setTitre('Titre valide');
            $reclamation->setContenu('Contenu valide de la réclamation.');
            $reclamation->setPriorite($priorite);
            $reclamation->setStatut('en_attente');

            $this->assertTrue($this->manager->validate($reclamation));
        }
    }

    // ✅ Test 9 : Tous les statuts valides
    public function testTousStatutsValides(): void
    {
        foreach (['en_attente', 'en_cours', 'resolue', 'rejetee'] as $statut) {
            $reclamation = new Reclamation();
            $reclamation->setTitre('Titre valide');
            $reclamation->setContenu('Contenu valide de la réclamation.');
            $reclamation->setPriorite('Moyenne');
            $reclamation->setStatut($statut);

            $this->assertTrue($this->manager->validate($reclamation));
        }
    }
}