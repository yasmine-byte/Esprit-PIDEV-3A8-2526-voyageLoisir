<?php

namespace App\Controller;

use App\Repository\DestinationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route("/home", name: "app_home")]
    public function index(): Response
    {
        return $this->render("home/index.html.twig");
    }

    #[Route("/properties", name: "app_properties")]
    public function properties(): Response
    {
        return $this->render("home/properties.html.twig");
    }

    #[Route("/property-details", name: "app_property_details")]
    public function propertyDetails(): Response
    {
        return $this->render("home/property-details.html.twig");
    }

    #[Route("/contact", name: "app_contact")]
    public function contact(): Response
    {
        return $this->render("home/contact.html.twig");
    }

    #[Route("/destinations", name: "app_destinations")]
    public function destinations(DestinationRepository $repo): Response
    {
        return $this->render("home/destinations.html.twig", [
            "destinations" => $repo->findAll(),
        ]);
    }

    #[Route("/destinations/{id}", name: "app_destination_detail")]
    public function destinationDetail(int $id, DestinationRepository $repo): Response
    {
        $destination = $repo->find($id);
        if (!$destination) {
            return $this->redirectToRoute("app_destinations");
        }
        return $this->render("home/destination-detail.html.twig", [
            "destination" => $destination,
        ]);
    }
}
