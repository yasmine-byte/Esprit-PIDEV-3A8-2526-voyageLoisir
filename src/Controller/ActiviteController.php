<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Form\ActiviteType;
use App\Repository\ActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/activite')]
final class ActiviteController extends AbstractController
{
    #[Route(name: 'app_activite_index', methods: ['GET'])]
    public function index(
        Request $request,
        ActiviteRepository $activiteRepository,
        HttpClientInterface $client
    ): Response {
        $lieu = $request->query->get('lieu');
        $type = $request->query->get('type');
        $prixMax = $request->query->get('prix_max');
        $dureeMax = $request->query->get('duree_max');
        $sort = $request->query->get('sort');

        $qb = $activiteRepository->createQueryBuilder('a');

        if (!empty($lieu)) {
            $qb->andWhere('a.lieu LIKE :lieu')
               ->setParameter('lieu', '%' . $lieu . '%');
        }

        if (!empty($type)) {
            $qb->andWhere('a.type LIKE :type')
               ->setParameter('type', '%' . $type . '%');
        }

        if ($prixMax !== null && $prixMax !== '') {
            $qb->andWhere('a.prix <= :prixMax')
               ->setParameter('prixMax', (float) $prixMax);
        }

        if ($dureeMax !== null && $dureeMax !== '') {
            $qb->andWhere('a.duree <= :dureeMax')
               ->setParameter('dureeMax', (int) $dureeMax);
        }

        if ($sort === 'prix_asc') {
            $qb->orderBy('a.prix', 'ASC');
        } elseif ($sort === 'prix_desc') {
            $qb->orderBy('a.prix', 'DESC');
        } else {
            $qb->orderBy('a.id', 'DESC');
        }

        $activites = $qb->getQuery()->getResult();

        // Fallback rates so conversion still works if API fails
        $currencyRates = [
            'TND_TO_EUR' => 0.295,
            'TND_TO_USD' => 0.344,
        ];

        try {
            // Request latest rates with EUR as base
            $response = $client->request('GET', 'https://api.frankfurter.dev/v1/latest', [
                'query' => [
                    'base' => 'EUR',
                    'symbols' => 'TND,USD',
                ],
                'timeout' => 15,
            ]);

            $data = $response->toArray(false);

            $eurToTnd = isset($data['rates']['TND']) ? (float) $data['rates']['TND'] : null;
            $eurToUsd = isset($data['rates']['USD']) ? (float) $data['rates']['USD'] : null;

            if ($eurToTnd && $eurToUsd && $eurToTnd > 0) {
                // 1 TND -> EUR
                $currencyRates['TND_TO_EUR'] = round(1 / $eurToTnd, 6);

                // 1 TND -> USD
                $currencyRates['TND_TO_USD'] = round($eurToUsd / $eurToTnd, 6);
            }
        } catch (\Throwable $e) {
            // keep fallback rates
        }

        return $this->render('activite/index.html.twig', [
            'activites' => $activites,
            'current_sort' => $sort,
            'currency_rates' => $currencyRates,
        ]);
    }

    #[Route('/new', name: 'app_activite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $activite = new Activite();
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($activite);
            $entityManager->flush();

            return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('activite/new.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_activite_show', methods: ['GET'])]
    public function show(Activite $activite): Response
    {
        return $this->render('activite/show.html.twig', [
            'activite' => $activite,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_activite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Activite $activite, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('activite/edit.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_activite_delete', methods: ['POST'])]
    public function delete(Request $request, Activite $activite, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $activite->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($activite);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
    }
}