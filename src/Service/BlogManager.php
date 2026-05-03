<?php

namespace App\Service;

use App\Entity\Blog;

class BlogManager
{
    public function validate(Blog $blog): bool
    {
        if (empty(trim($blog->getTitre()))) {
            throw new \InvalidArgumentException('Le titre du blog est obligatoire.');
        }

        if (empty(trim($blog->getContenu()))) {
            throw new \InvalidArgumentException('Le contenu du blog est obligatoire.');
        }

        if (mb_strlen(trim($blog->getTitre())) < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caractères.');
        }

        if (mb_strlen(trim($blog->getContenu())) < 10) {
            throw new \InvalidArgumentException('Le contenu doit contenir au moins 10 caractères.');
        }

        if ($blog->getDatePublication() !== null && $blog->getDateCreation() !== null) {
            if ($blog->getDatePublication() < $blog->getDateCreation()) {
                throw new \InvalidArgumentException('La date de publication ne peut pas être antérieure à la date de création.');
            }
        }

        if ($blog->getRatingAverage() !== null) {
            if ($blog->getRatingAverage() < 0 || $blog->getRatingAverage() > 5) {
                throw new \InvalidArgumentException('Le rating moyen doit être compris entre 0 et 5.');
            }
        }

        return true;
    }
}