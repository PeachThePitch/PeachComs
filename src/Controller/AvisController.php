<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Avis;
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
    public function laisser(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $u = $this->getUser();

        // Récupérer les commandes terminées sans avis
        $commandesEligibles = array_filter(
            $u->getCommandes()->toArray(),
            fn($c) => $c->getEtat() === 'Terminée' && count($c->getAvis()) === 0
        );

        if (empty($commandesEligibles)) {
            return $this->render('avis/non_eligible.html.twig');
        }

        if ($request->isMethod('POST')) {
            $commandeId = $request->request->get('commande_id');
            $commande = $em->getRepository(Commande::class)->find($commandeId);

            if (!$commande || $commande->getUser() !== $u || $commande->getEtat() !== 'Terminée' || count($commande->getAvis()) > 0) {
                return $this->redirectToRoute('app_avis');
            }

            $note = max(1, min(5, (int) $request->request->get('note')));
            $fichier = $request->files->get('image');

            $avis = new Avis();
            $avis->setNote($note);
            $avis->setCommentaire($request->request->get('commentaire'));
            $avis->setDateAvis(new \DateTime());
            $avis->setValide(false);
            $avis->setUser($u);
            $avis->setCommande($commande);

            if ($fichier) {
                $nomFichier = uniqid() . '.' . $fichier->guessExtension();
                $fichier->move(
                    $this->getParameter('kernel.project_dir') . '/public/images/avis/',
                    $nomFichier
                );
                $avis->setImage($nomFichier);
            }

            $em->persist($avis);
            $em->flush();

            return $this->render('avis/merci.html.twig');
        }

        return $this->render('avis/laisser.html.twig', [
            'commandes' => $commandesEligibles,
        ]);
    }
}


