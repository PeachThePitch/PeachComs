<?php

namespace App\Controller;

use App\Entity\Ajouter;
use App\Entity\Panier;
use App\Entity\Produit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PanierController extends AbstractController
{
    #[Route('/panier', name: 'app_panier')]
    public function index(): Response
    {
        $u = $this->getUser();
        $panier = $u->getPanier();

        return $this->render('panier/index.html.twig', [
            'panier' => $panier,
        ]);
    }

    #[Route('/panier/ajouter/{id}', name: 'app_panier_ajouter')]
public function ajouter(Produit $produit, Request $request, EntityManagerInterface $em): Response
{
    $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

    $u = $this->getUser();
    $quantite = max(1, (int) $request->request->get('quantite', 1));

    if (!$u->getPanier()) {
        $panier = new Panier();
        $u->setPanier($panier);
        $em->persist($panier);
    }

    $panier = $u->getPanier();

    $ligneExistante = null;
    foreach ($panier->getAjouters() as $ajouter) {
        if ($ajouter->getProduit() === $produit) {
            $ligneExistante = $ajouter;
            break;
        }
    }

    if ($ligneExistante) {
        $ligneExistante->setQuantite($ligneExistante->getQuantite() + $quantite);
    } else {
        $ajouter = new Ajouter();
        $ajouter->setProduit($produit);
        $ajouter->setPanier($panier);
        $ajouter->setQuantite($quantite);
        $em->persist($ajouter);
    }

    $em->flush();

    $referer = $request->headers->get('referer');
    return $this->redirect($referer ?? $this->generateUrl('app_panier'));
}

    #[Route('/panier/retirer/{id}', name: 'app_panier_retirer')]
    public function retirer(Ajouter $ajouter, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($ajouter->getQuantite() > 1) {
            $ajouter->setQuantite($ajouter->getQuantite() - 1);
        } else {
            $em->remove($ajouter);
        }

        $em->flush();

        return $this->redirectToRoute('app_panier');
    }

    #[Route('/panier/supprimer/{id}', name: 'app_panier_supprimer')]
    public function supprimer(Ajouter $ajouter, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $em->remove($ajouter);
        $em->flush();

        return $this->redirectToRoute('app_panier');
    }
}