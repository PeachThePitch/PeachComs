<?php

namespace App\Controller;

use App\Repository\AvisRepository;
use App\Service\UploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AvisController extends AbstractController
{
    #[Route('/avis', name: 'app_avis')]
    public function index(AvisRepository $avisRepository): Response
    {
        $avis = $avisRepository->findBy(['valide' => true], ['dateAvis' => 'DESC']);

        // Calcul de la note moyenne
        $moyenne = 0;
        if (count($avis) > 0) {
            $moyenne = array_sum(array_map(fn($a) => $a->getNote(), $avis)) / count($avis);
        }

        return $this->render('avis/index.html.twig', [
            'avis' => $avis,
            'moyenne' => round($moyenne, 1),
        ]);
    }

    #[Route('/avis/laisser', name: 'app_avis_laisser')]
    public function laisser(Request $request, EntityManagerInterface $em, UploadService $uploadService): Response
    {
        // ... code existant ...

        if ($request->isMethod('POST')) {
            // ... code existant ...

            $fichier = $request->files->get('image');

            if ($fichier) {
                $erreur = $uploadService->valider($fichier);
                if ($erreur) {
                    return $this->render('avis/laisser.html.twig', [
                        'commandes' => $commandesEligibles,
                        'erreur' => $erreur,
                    ]);
                }
                $nomFichier = $uploadService->upload(
                    $fichier,
                    $this->getParameter('kernel.project_dir') . '/public/images/avis/'
                );
                $avis->setImage($nomFichier);

            }
        }
    }
}
