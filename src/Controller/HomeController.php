<?php

namespace App\Controller;

use App\Repository\HebergementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(HebergementRepository $hebergementRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'hebergements' => $hebergementRepository->findAll(),
        ]);
    }

    #[Route('/properties', name: 'app_properties')]
    public function properties(HebergementRepository $hebergementRepository): Response
    {
        return $this->render('home/properties.html.twig', [
            'hebergements' => $hebergementRepository->findAll(),
        ]);
    }

    #[Route('/property-details/{id}', name: 'app_property_details')]
    public function propertyDetails(int $id, HebergementRepository $hebergementRepository): Response
    {
        $hebergement = $hebergementRepository->find($id);
        return $this->render('home/property-details.html.twig', [
            'hebergement' => $hebergement,
        ]);
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('home/contact.html.twig');
    }
}