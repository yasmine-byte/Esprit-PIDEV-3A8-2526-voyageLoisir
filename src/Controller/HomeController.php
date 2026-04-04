<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
    #[Route('/properties', name: 'app_properties')]
    public function properties(): Response
    {
        return $this->render('home/properties.html.twig');
    }

    #[Route('/property-details', name: 'app_property_details')]
    public function propertyDetails(): Response
    {
        return $this->render('home/property-details.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('home/contact.html.twig');
    }
}