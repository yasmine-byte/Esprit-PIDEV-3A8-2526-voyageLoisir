<?php
namespace App\Controller;

use App\Repository\DestinationRepository;
use App\Repository\HebergementRepository;
use App\Repository\TypeRepository;
use App\Repository\ActiviteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        HebergementRepository $hebergementRepository,
        DestinationRepository $destinationRepository,
        ActiviteRepository $activiteRepository
    ): Response {
        return $this->render('home/index.html.twig', [
            'hebergements' => $hebergementRepository->findAll(),
            'destinations' => $destinationRepository->findAll(),
            'activites'    => $activiteRepository->findAll(),
        ]);
    }

    #[Route('/destinations', name: 'app_destinations')]
    public function destinations(Request $request, DestinationRepository $repo): Response
    {
        $search = $request->query->get('search', '');
        $saison = $request->query->get('saison', '');
        $destinations = $repo->findByFilters($search, $saison, '', 'id', 'ASC');

        return $this->render('home/destinations.html.twig', [
            'destinations' => $destinations,
            'search'       => $search,
            'saison'       => $saison,
        ]);
    }

    #[Route('/destinations/{id}', name: 'app_destination_detail')]
    public function destinationDetail(int $id, DestinationRepository $repo): Response
    {
        $destination = $repo->find($id);

        if (!$destination) {
            return $this->redirectToRoute('app_destinations');
        }

        return $this->render('home/destination-detail.html.twig', [
            'destination' => $destination,
        ]);
    }

    #[Route('/hebergements', name: 'app_hebergements_front')]
    public function hebergements(
        Request $request,
        HebergementRepository $hebergementRepository,
        TypeRepository $typeRepository
    ): Response {
        $description  = $request->query->get('description');
        $typeId       = $request->query->get('type') ? (int)$request->query->get('type') : null;
        $prixMin      = $request->query->get('prixMin') ? (float)$request->query->get('prixMin') : null;
        $prixMax      = $request->query->get('prixMax') ? (float)$request->query->get('prixMax') : null;
        $tri          = $request->query->get('tri');
        $dateDebutStr = $request->query->get('dateDebut');
        $dateFinStr   = $request->query->get('dateFin');
        $dateDebut    = $dateDebutStr ? new \DateTime($dateDebutStr) : null;
        $dateFin      = $dateFinStr   ? new \DateTime($dateFinStr)   : null;

        $hebergements = $hebergementRepository->search(
            $description, $typeId, $prixMin, $prixMax, $tri, $dateDebut, $dateFin
        );

        return $this->render('home/properties.html.twig', [
            'hebergements' => $hebergements,
            'types'        => $typeRepository->findAll(),
            'filters'      => [
                'description' => $description,
                'type'        => $typeId,
                'prixMin'     => $prixMin,
                'prixMax'     => $prixMax,
                'tri'         => $tri,
                'dateDebut'   => $dateDebutStr,
                'dateFin'     => $dateFinStr,
            ],
        ]);
    }

    #[Route('/properties', name: 'app_properties')]
    public function properties(
        Request $request,
        HebergementRepository $hebergementRepository,
        TypeRepository $typeRepository
    ): Response {
        $description  = $request->query->get('description');
        $typeId       = $request->query->get('type') ? (int)$request->query->get('type') : null;
        $prixMin      = $request->query->get('prixMin') ? (float)$request->query->get('prixMin') : null;
        $prixMax      = $request->query->get('prixMax') ? (float)$request->query->get('prixMax') : null;
        $tri          = $request->query->get('tri');
        $dateDebutStr = $request->query->get('dateDebut');
        $dateFinStr   = $request->query->get('dateFin');
        $dateDebut    = $dateDebutStr ? new \DateTime($dateDebutStr) : null;
        $dateFin      = $dateFinStr   ? new \DateTime($dateFinStr)   : null;

        $hebergements = $hebergementRepository->search(
            $description, $typeId, $prixMin, $prixMax, $tri, $dateDebut, $dateFin
        );

        return $this->render('home/properties.html.twig', [
            'hebergements' => $hebergements,
            'types'        => $typeRepository->findAll(),
            'filters'      => [
                'description' => $description,
                'type'        => $typeId,
                'prixMin'     => $prixMin,
                'prixMax'     => $prixMax,
                'tri'         => $tri,
                'dateDebut'   => $dateDebutStr,
                'dateFin'     => $dateFinStr,
            ],
        ]);
    }

    #[Route('/property-details/{id}', name: 'app_property_details')]
    public function propertyDetails(int $id, HebergementRepository $hebergementRepository): Response
    {
        $hebergement = $hebergementRepository->find($id);

        if (!$hebergement) {
            throw $this->createNotFoundException('Hébergement introuvable.');
        }

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