<?php
namespace App\Controller;

use App\Repository\HebergementRepository;
use App\Repository\TypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function properties(
        Request $request,
        HebergementRepository $hebergementRepository,
        TypeRepository $typeRepository
    ): Response {
        $description = $request->query->get('description');
        $typeId = $request->query->get('type') ? (int)$request->query->get('type') : null;
        $prixMin = $request->query->get('prixMin') ? (float)$request->query->get('prixMin') : null;
        $prixMax = $request->query->get('prixMax') ? (float)$request->query->get('prixMax') : null;
        $tri = $request->query->get('tri');
        $dateDebutStr = $request->query->get('dateDebut');
        $dateFinStr = $request->query->get('dateFin');

        $dateDebut = $dateDebutStr ? new \DateTime($dateDebutStr) : null;
        $dateFin = $dateFinStr ? new \DateTime($dateFinStr) : null;

        $hebergements = $hebergementRepository->search(
            $description, $typeId, $prixMin, $prixMax, $tri, $dateDebut, $dateFin
        );

        return $this->render('home/properties.html.twig', [
            'hebergements' => $hebergements,
            'types' => $typeRepository->findAll(),
            'filters' => [
                'description' => $description,
                'type' => $typeId,
                'prixMin' => $prixMin,
                'prixMax' => $prixMax,
                'tri' => $tri,
                'dateDebut' => $dateDebutStr,
                'dateFin' => $dateFinStr,
            ],
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