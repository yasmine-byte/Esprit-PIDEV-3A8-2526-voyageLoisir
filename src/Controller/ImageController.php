<?php

namespace App\Controller;

use App\Entity\Image;
use App\Form\ImageType;
use App\Repository\ImageRepository;
use App\Repository\DestinationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/image")]
class ImageController extends AbstractController
{
    #[Route("/", name: "app_image_index", methods: ["GET"])]
public function index(Request $request, ImageRepository $repo): Response
{
    $search = $request->query->get('search', '');
    $tri    = $request->query->get('tri', 'i.id');
    $ordre  = $request->query->get('ordre', 'ASC');

    $images = $repo->findByFilters($search, $tri, $ordre);

    return $this->render("image/index.html.twig", [
        'images' => $images,
        'search' => $search,
        'tri'    => $tri,
        'ordre'  => $ordre,
    ]);
}

    #[Route("/new", name: "app_image_new", methods: ["GET", "POST"])]
    public function new(Request $request, EntityManagerInterface $em, DestinationRepository $destRepo): Response
    {
        $image = new Image();

        $destinationId = $request->query->getInt('destination_id');
        if ($destinationId) {
            $destination = $destRepo->find($destinationId);
            if ($destination) {
                $image->setDestination($destination);
            }
        }

        $form  = $this->createForm(ImageType::class, $image);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $files       = $form->get("url_image")->getData();
            $destination = $form->get("destination")->getData();
            $uploadDir   = $this->getParameter("kernel.project_dir") . "/public/uploads/images";

            foreach ($files as $file) {
                $filename = uniqid("img_") . "." . $file->guessExtension();
                $file->move($uploadDir, $filename);

                $img = new Image();
                $img->setUrlImage("/uploads/images/" . $filename);
                $img->setDestination($destination);
                $em->persist($img);
            }

            $em->flush();
            $this->addFlash("success", count($files) . " image(s) ajoutee(s) avec succes.");
            return $this->redirectToRoute("app_image_index");
        }

        return $this->render("image/new.html.twig", [
            "image"         => $image,
            "form"          => $form,
            "destination_id" => $destinationId,
        ]);
    }

    #[Route("/{id}", name: "app_image_show", methods: ["GET"])]
    public function show(Image $image): Response
    {
        return $this->render("image/show.html.twig", ["image" => $image]);
    }

    #[Route("/{id}/edit", name: "app_image_edit", methods: ["GET", "POST"])]
public function edit(Request $request, Image $image, EntityManagerInterface $em, ImageRepository $repo): Response
{
    $form = $this->createForm(ImageType::class, $image);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $files       = $form->get("url_image")->getData();
        $destination = $form->get("destination")->getData();

        if ($files && count($files) > 0) {
            $uploadDir = $this->getParameter("kernel.project_dir") . "/public/uploads/images";

            // Supprimer l'ancienne image principale
            $old = $image->getUrlImage();
            if ($old) {
                $full = $this->getParameter("kernel.project_dir") . "/public" . $old;
                if (file_exists($full)) unlink($full);
            }

            // Première image → remplace l'image actuelle
            $firstFile = array_shift($files);
            $filename  = uniqid("img_") . "." . $firstFile->guessExtension();
            $firstFile->move($uploadDir, $filename);
            $image->setUrlImage("/uploads/images/" . $filename);
            $image->setDestination($destination);
            $em->persist($image);

            // Images supplémentaires → nouvelles entités
            foreach ($files as $file) {
                $filename = uniqid("img_") . "." . $file->guessExtension();
                $file->move($uploadDir, $filename);

                $img = new Image();
                $img->setUrlImage("/uploads/images/" . $filename);
                $img->setDestination($destination);
                $em->persist($img);
            }
        } else {
            // Pas de nouveau fichier → on garde l'image, on met à jour la destination
            $image->setDestination($destination);
        }

        $em->flush();
        $this->addFlash("success", "Image(s) modifiee(s) avec succes.");
        return $this->redirectToRoute("app_image_index");
    }

    return $this->render("image/edit.html.twig", [
        "image" => $image,
        "form"  => $form,
    ]);
}
    #[Route("/{id}", name: "app_image_delete", methods: ["POST"])]
    public function delete(Request $request, Image $image, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid("delete" . $image->getId(), $request->getPayload()->getString("_token"))) {
            $path = $image->getUrlImage();
            if ($path) {
                $full = $this->getParameter("kernel.project_dir") . "/public" . $path;
                if (file_exists($full)) unlink($full);
            }
            $em->remove($image);
            $em->flush();
            $this->addFlash("success", "Image supprimee.");
        }
        return $this->redirectToRoute("app_image_index");
    }
}
