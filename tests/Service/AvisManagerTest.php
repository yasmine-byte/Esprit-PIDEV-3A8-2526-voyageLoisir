<?php
namespace App\Tests\Service;

use App\Entity\Avis;
use App\Service\AvisManager;
use PHPUnit\Framework\TestCase;

class AvisManagerTest extends TestCase
{
    private function makeAvis(): Avis
    {
        $a = new Avis();
        $a->setContenu('Très belle expérience, je recommande vivement cet hébergement.');
        $a->setNbEtoiles(5);
        $a->setStatut('En attente');
        $a->setSentimentLabel('positive');
        $a->setUserId(1);
        return $a;
    }

    // ✅ TEST 1 : Avis valide complet
    public function testAvisValide(): void
    {
        $this->assertTrue((new AvisManager())->validate($this->makeAvis()));
    }

    // ❌ TEST 2 : Contenu obligatoire
    public function testContenuObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu de l\'avis est obligatoire.');
        $a = $this->makeAvis();
        $a->setContenu('');
        (new AvisManager())->validerContenu($a);
    }

    // ❌ TEST 3 : Contenu trop court
    public function testContenuTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu doit contenir au moins 10 caractères.');
        $a = $this->makeAvis();
        $a->setContenu('Court');
        (new AvisManager())->validerContenu($a);
    }

    // ❌ TEST 4 : Note trop basse (zéro)
    public function testNoteZeroInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note minimum est 1 étoile.');
        $a = $this->makeAvis();
        $a->setNbEtoiles(0);
        (new AvisManager())->validerNbEtoiles($a);
    }

    // ❌ TEST 5 : Note trop haute (6 étoiles)
    public function testNoteSixInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note maximum est 5 étoiles.');
        $a = $this->makeAvis();
        $a->setNbEtoiles(6);
        (new AvisManager())->validerNbEtoiles($a);
    }

    // ✅ TEST 6 : Note 1 valide (minimum)
    public function testNoteUnValide(): void
    {
        $a = $this->makeAvis();
        $a->setNbEtoiles(1);
        $this->assertTrue((new AvisManager())->validerNbEtoiles($a));
    }

    // ✅ TEST 7 : Note 5 valide (maximum)
    public function testNoteCinqValide(): void
    {
        $a = $this->makeAvis();
        $a->setNbEtoiles(5);
        $this->assertTrue((new AvisManager())->validerNbEtoiles($a));
    }

    // ❌ TEST 8 : Statut invalide
    public function testStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut de l\'avis est invalide.');
        $a = $this->makeAvis();
        $a->setStatut('Archivé');
        (new AvisManager())->validerStatut($a);
    }

    // ✅ TEST 9 : Statut validé valide
    public function testStatutValideValide(): void
    {
        $a = $this->makeAvis();
        $a->setStatut('Validé');
        $this->assertTrue((new AvisManager())->validerStatut($a));
    }

    // ❌ TEST 10 : Sentiment invalide
    public function testSentimentInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le sentiment doit être : positive, negative ou neutral.');
        $a = $this->makeAvis();
        $a->setSentimentLabel('mitigé');
        (new AvisManager())->validerSentiment($a);
    }

    // ✅ TEST 11 : Sentiment null accepté (pas encore analysé)
    public function testSentimentNullAccepte(): void
    {
        $a = $this->makeAvis();
        $a->setSentimentLabel(null);
        $this->assertTrue((new AvisManager())->validerSentiment($a));
    }

    // ✅ TEST 12 : Sentiment negative valide
    public function testSentimentNegativeValide(): void
    {
        $a = $this->makeAvis();
        $a->setSentimentLabel('negative');
        $this->assertTrue((new AvisManager())->validerSentiment($a));
    }

    // ❌ TEST 13 : UserId zéro invalide
    public function testUserIdZeroInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'identifiant utilisateur est invalide.');
        $a = $this->makeAvis();
        $a->setUserId(0);
        (new AvisManager())->validerUserId($a);
    }

    // ✅ TEST 14 : UserId valide
    public function testUserIdValide(): void
    {
        $a = $this->makeAvis();
        $a->setUserId(3);
        $this->assertTrue((new AvisManager())->validerUserId($a));
    }
}
