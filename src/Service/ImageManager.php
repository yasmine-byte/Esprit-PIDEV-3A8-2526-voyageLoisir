<?php

namespace App\Service;

use App\Entity\Image;

class ImageManager
{
    public function validate(Image $image): bool
    {
        // url_image obligatoire
        if (empty($image->getUrlImage())) {
            throw new \InvalidArgumentException("L'URL de l'image est obligatoire.");
        }

        // url_image : max 255 caractères
        if (strlen($image->getUrlImage()) > 255) {
            throw new \InvalidArgumentException("L'URL de l'image ne peut pas dépasser 255 caractères.");
        }

        // destination obligatoire
        if ($image->getDestination() === null) {
            throw new \InvalidArgumentException('La destination est obligatoire.');
        }

        return true;
    }
}