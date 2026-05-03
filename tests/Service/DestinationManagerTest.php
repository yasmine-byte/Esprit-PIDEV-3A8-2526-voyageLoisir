<?php

namespace App\Tests\Service;

use App\Entity\Destination;
use App\Service\DestinationManager;
use PHPUnit\Framework\TestCase;

class DestinationManagerTest extends TestCase
{
    private function makeValidDestination(): Destination
    {
        $d = new Destination();
        $d->setNom('Paris');
        $d->setPays('France');
        $d->setDescription('Une belle ville avec beaucoup de monuments historiques et culturels.');
        $d->setStatut(true);
        $d->setMeilleureSaison('Printemps');
        $d->setLatitude(48.85);
        $d->setLongitude(2.35);
        $d->setNbVisites(100);
        return $d;
    }

    // ✅ Destination active (statut = true)
    public function testDestinationActive(): void
    {
        $d = $this->makeValidDestination();
        $d->setStatut(true);
        (new DestinationManager())->validate($d);
        $this->assertTrue($d->isStatut());
    }

    // ✅ Destination inactive (statut = false)
    public function testDestinationInactive(): void
    {
        $d = $this->makeValidDestination();
        $d->setStatut(false);
        (new DestinationManager())->validate($d);
        $this->assertFalse($d->isStatut());
    }

    // ❌ Statut null → exception
    public function testStatutNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut est obligatoire.');
        $d = $this->makeValidDestination();
        $d->setStatut(null);
        (new DestinationManager())->validate($d);
    }

    // ❌ Nom vide
    public function testNomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire.');
        $d = $this->makeValidDestination();
        $d->setNom('');
        (new DestinationManager())->validate($d);
    }

    // ❌ Nom trop court
    public function testNomTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('au moins 2 caractères');
        $d = $this->makeValidDestination();
        $d->setNom('A');
        (new DestinationManager())->validate($d);
    }

    // ❌ Nom trop long
    public function testNomTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('100 caractères');
        $d = $this->makeValidDestination();
        $d->setNom(str_repeat('A', 101));
        (new DestinationManager())->validate($d);
    }

    // ❌ Nom avec chiffres
    public function testNomAvecChiffres(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('chiffres');
        $d = $this->makeValidDestination();
        $d->setNom('Paris123');
        (new DestinationManager())->validate($d);
    }

    // ❌ Pays vide
    public function testPaysVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le pays est obligatoire.');
        $d = $this->makeValidDestination();
        $d->setPays('');
        (new DestinationManager())->validate($d);
    }

    // ❌ Pays trop court
    public function testPaysTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->makeValidDestination();
        $d->setPays('F');
        (new DestinationManager())->validate($d);
    }

    // ❌ Pays avec chiffres
    public function testPaysAvecChiffres(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->makeValidDestination();
        $d->setPays('France2');
        (new DestinationManager())->validate($d);
    }

    // ❌ Description vide
    public function testDescriptionVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description est obligatoire.');
        $d = $this->makeValidDestination();
        $d->setDescription('');
        (new DestinationManager())->validate($d);
    }

    // ❌ Description trop courte
    public function testDescriptionTropCourte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('20 caractères');
        $d = $this->makeValidDestination();
        $d->setDescription('Trop court.');
        (new DestinationManager())->validate($d);
    }

    // ❌ Description trop longue
    public function testDescriptionTropLongue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('2000 caractères');
        $d = $this->makeValidDestination();
        $d->setDescription(str_repeat('A', 2001));
        (new DestinationManager())->validate($d);
    }

    // ❌ Saison vide
    public function testSaisonVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La saison est obligatoire.');
        $d = $this->makeValidDestination();
        $d->setMeilleureSaison('');
        (new DestinationManager())->validate($d);
    }

    // ❌ Saison invalide
    public function testSaisonInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Saison invalide.');
        $d = $this->makeValidDestination();
        $d->setMeilleureSaison('Monsoon');
        (new DestinationManager())->validate($d);
    }

    // ✅ Toutes les saisons valides + statut true
    public function testSaisonsValides(): void
    {
        $manager = new DestinationManager();
        foreach (['Printemps', 'Ete', 'Automne', 'Hiver'] as $saison) {
            $d = $this->makeValidDestination();
            $d->setMeilleureSaison($saison);
            $manager->validate($d);
            $this->assertTrue($d->isStatut());
        }
    }

    // ❌ Latitude null
    public function testLatitudeNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La latitude est obligatoire.');
        $d = $this->makeValidDestination();
        $d->setLatitude(null);
        (new DestinationManager())->validate($d);
    }

    // ❌ Latitude < -90
    public function testLatitudeTropPetite(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('-90 et 90');
        $d = $this->makeValidDestination();
        $d->setLatitude(-91.0);
        (new DestinationManager())->validate($d);
    }

    // ❌ Latitude > 90
    public function testLatitudeTropGrande(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->makeValidDestination();
        $d->setLatitude(91.0);
        (new DestinationManager())->validate($d);
    }

    // ❌ Longitude null
    public function testLongitudeNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La longitude est obligatoire.');
        $d = $this->makeValidDestination();
        $d->setLongitude(null);
        (new DestinationManager())->validate($d);
    }

    // ❌ Longitude < -180
    public function testLongitudeTropPetite(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('-180 et 180');
        $d = $this->makeValidDestination();
        $d->setLongitude(-181.0);
        (new DestinationManager())->validate($d);
    }

    // ❌ Longitude > 180
    public function testLongitudeTropGrande(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->makeValidDestination();
        $d->setLongitude(181.0);
        (new DestinationManager())->validate($d);
    }

    // ❌ NbVisites null
    public function testNbVisitesNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de visites est obligatoire.');
        $d = $this->makeValidDestination();
        $d->setNbVisites(null);
        (new DestinationManager())->validate($d);
    }

    // ❌ NbVisites négatif
    public function testNbVisitesNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('zéro ou positif');
        $d = $this->makeValidDestination();
        $d->setNbVisites(-1);
        (new DestinationManager())->validate($d);
    }

    // ✅ NbVisites = 0 + statut false
    public function testNbVisitesZeroStatutFalse(): void
    {
        $d = $this->makeValidDestination();
        $d->setNbVisites(0);
        $d->setStatut(false);
        (new DestinationManager())->validate($d);
        $this->assertFalse($d->isStatut());
    }
}