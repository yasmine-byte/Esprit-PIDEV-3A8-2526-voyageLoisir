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

#[Route('/activite')]
final class ActiviteController extends AbstractController
{
    #[Route(name: 'app_activite_index', methods: ['GET'])]
public function index(Request $request, ActiviteRepository $activiteRepository): Response
{
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

    if (!empty($prixMax)) {
        $qb->andWhere('a.prix <= :prixMax')
           ->setParameter('prixMax', (float) $prixMax);
    }

    if (!empty($dureeMax)) {
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

    return $this->render('activite/index.html.twig', [
        'activites' => $activites,
        'current_sort' => $sort,
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