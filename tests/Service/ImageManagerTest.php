<?php

namespace App\Tests\Service;

use App\Entity\Image;
use App\Entity\Destination;
use App\Service\ImageManager;
use PHPUnit\Framework\TestCase;

class ImageManagerTest extends TestCase
{
    private function makeValidImage(): Image
    {
        $destination = new Destination();
        $destination->setNom('Paris');
        $destination->setPays('France');
        $destination->setDescription('Une belle ville avec beaucoup de monuments historiques et culturels.');
        $destination->setStatut(true);
        $destination->setMeilleureSaison('Printemps');
        $destination->setLatitude(48.85);
        $destination->setLongitude(2.35);
        $destination->setNbVisites(10);

        $image = new Image();
        $image->setUrlImage('https://example.com/paris.jpg');
        $image->setDestination($destination);
        return $image;
    }

    // ✅ Cas valide
    public function testImageValide(): void
    {
        $this->assertTrue((new ImageManager())->validate($this->makeValidImage()));
    }

    // ❌ URL vide
    public function testUrlImageVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("L'URL de l'image est obligatoire.");
        $img = $this->makeValidImage();
        $img->setUrlImage('');
        (new ImageManager())->validate($img);
    }

    // ❌ URL trop longue (> 255 caractères)
    public function testUrlImageTropLongue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('255 caractères');
        $img = $this->makeValidImage();
        $img->setUrlImage('https://example.com/' . str_repeat('a', 240) . '.jpg');
        (new ImageManager())->validate($img);
    }

    // ✅ URL exactement 255 caractères (valide)
    public function testUrlImage255Caracteres(): void
    {
        $img = $this->makeValidImage();
        $img->setUrlImage(str_repeat('a', 255));
        $this->assertTrue((new ImageManager())->validate($img));
    }

    // ❌ Destination null
    public function testDestinationNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La destination est obligatoire.');
        $img = $this->makeValidImage();
        $img->setDestination(null);
        (new ImageManager())->validate($img);
    }
}