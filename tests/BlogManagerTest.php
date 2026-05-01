<?php

namespace App\Tests;

use App\Entity\Blog;
use App\Service\BlogManager;
use PHPUnit\Framework\TestCase;

class BlogManagerTest extends TestCase
{
    private BlogManager $manager;

    protected function setUp(): void
    {
        $this->manager = new BlogManager();
    }

    // ✅ Test 1 : Blog valide complet
    public function testBlogValide(): void
    {
        $blog = new Blog();
        $blog->setTitre('Mon super article');
        $blog->setContenu('Voici le contenu de mon article de blog.');
        $blog->setStatus(true);

        $this->assertTrue($this->manager->validate($blog));
    }

    // ✅ Test 2 : Titre vide → exception
    public function testTitreObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre du blog est obligatoire.');

        $blog = new Blog();
        $blog->setTitre('');
        $blog->setContenu('Contenu valide du blog.');

        $this->manager->validate($blog);
    }

    // ✅ Test 3 : Contenu vide → exception
    public function testContenuObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu du blog est obligatoire.');

        $blog = new Blog();
        $blog->setTitre('Titre valide');
        $blog->setContenu('');

        $this->manager->validate($blog);
    }

    // ✅ Test 4 : Titre trop court → exception
    public function testTitreTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 3 caractères.');

        $blog = new Blog();
        $blog->setTitre('Ab');
        $blog->setContenu('Contenu valide du blog.');

        $this->manager->validate($blog);
    }

    // ✅ Test 5 : Contenu trop court → exception
    public function testContenuTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu doit contenir au moins 10 caractères.');

        $blog = new Blog();
        $blog->setTitre('Titre valide');
        $blog->setContenu('Court');

        $this->manager->validate($blog);
    }

    // ✅ Test 6 : Date publication avant date création → exception
    public function testDatePublicationInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de publication ne peut pas être antérieure à la date de création.');

        $blog = new Blog();
        $blog->setTitre('Titre valide');
        $blog->setContenu('Contenu valide du blog.');
        $blog->setDateCreation(new \DateTimeImmutable('2024-06-01'));
        $blog->setDatePublication(new \DateTimeImmutable('2024-01-01'));

        $this->manager->validate($blog);
    }

    // ✅ Test 7 : Rating invalide (> 5) → exception
    public function testRatingTropEleve(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le rating moyen doit être compris entre 0 et 5.');

        $blog = new Blog();
        $blog->setTitre('Titre valide');
        $blog->setContenu('Contenu valide du blog.');
        $blog->setRatingAverage(6.0);

        $this->manager->validate($blog);
    }

    // ✅ Test 8 : Rating invalide (< 0) → exception
    public function testRatingNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le rating moyen doit être compris entre 0 et 5.');

        $blog = new Blog();
        $blog->setTitre('Titre valide');
        $blog->setContenu('Contenu valide du blog.');
        $blog->setRatingAverage(-1.0);

        $this->manager->validate($blog);
    }

    // ✅ Test 9 : Rating null accepté
    public function testRatingNullAccepte(): void
    {
        $blog = new Blog();
        $blog->setTitre('Titre valide');
        $blog->setContenu('Contenu valide du blog.');
        $blog->setRatingAverage(null);

        $this->assertTrue($this->manager->validate($blog));
    }

    // ✅ Test 10 : Date publication valide (après création)
    public function testDatePublicationValide(): void
    {
        $blog = new Blog();
        $blog->setTitre('Titre valide');
        $blog->setContenu('Contenu valide du blog.');
        $blog->setDateCreation(new \DateTimeImmutable('2024-01-01'));
        $blog->setDatePublication(new \DateTimeImmutable('2024-06-01'));

        $this->assertTrue($this->manager->validate($blog));
    }
}