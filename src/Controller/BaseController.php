<?php

namespace App\Controller;

use App\Entity\Ajouter;
use App\Entity\Panier;
use App\Entity\Produit;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BaseController extends AbstractController
{
    #[Route('/', name: 'app_accueil')]
    public function index(): Response
    {
        return $this->render('base/index.html.twig', [

        ]);
    }

    #[Route('/mentions-legales', name: 'app_mentions_legales')]
    public function mentionsLegales(): Response
    {
        return $this->render('base/mentions_legales.html.twig');
    }

    #[Route('/commissions', name: 'app_commissions')]
    public function commissions(ProduitRepository $produitRepository, Request $request): Response
    {
        $type = $request->query->get('type');
        $style = $request->query->get('style');

        $criteria = [];
        if ($type) {
            $criteria['type'] = $type;
        }

        if ($style) {
            $criteria['style'] = $style;
        }

        $produits = $produitRepository->findBy($criteria);

        return $this->render('base/commissions.html.twig', [
            'produits' => $produits,
            'typeActif' => $type,
            'styleActif' => $style,
        ]);
    }

    #[Route('/commission/{id}', name: 'app_commission_detail')]
    public function detail(Produit $produit): Response
    {
        return $this->render('base/detail.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/private-mes-favoris', name: 'app_mes_favoris')]
    public function mesFavoris(): Response
    {
        $produits = $this->getUser()->getProduits();
        return $this->render('base/favoris.html.twig', [
            'produits' => $produits,
        ]);
    }

    #[Route('/private-favoris/{id}', name: 'app_favoris')]
    public function favoris(Produit $produit, Request $request, EntityManagerInterface $em): Response
    {
        $u = $this->getUser();
        if ($u->getProduits()->contains($produit)) {
            $u->removeProduit($produit);
        } else {
            $u->addProduit($produit);
        }
        $em->persist($u);
        $em->flush();
        return $this->redirect($referer ?? $this->GenerateUrl('app_commissions'));
    }

    #[Route('/commandes', name: 'app_commandes')]
    public function commandes(): Response
    {
        return $this->render('base/commandes.html.twig', [

        ]);
    }

    #[Route('/private-panier/{id}', name: 'app_panier')]
    public function panier(Request $request, Produit $produit, EntityManagerInterface $em): Response
    {
        $referer = $request->headers->get('referer');
        $u = $this->getUser();
        $panier = $u->getPanier();

        if (!$panier) {
            $panier = new Panier();
            $u->setPanier($panier);
        }
        $trouver = false;
        $ajouterTrouver = null;

        $total = $panier->getAjouters()->count();
        $i = 0;
        if ($total) { /* ( $total > 0 ) == ($total) */
            do {
                $ajouter = $panier->getAjouters()->get($i);
                $i++;
                if ($ajouter->getProduit() == $produit) {
                    $trouver = true;
                    $ajouterTrouver = $ajouter;
                }
            } while (!$trouver && ($i < $total));
        }

        /*foreach($panier->getAjouters() as $ajouter){

        }*/
        if ($trouver) {
            $ajouter = $ajouterTrouver;
            $ajouter->setQuantite($ajouter->getQuantite() + 1);
        } else {
            $ajouter = new Ajouter;
            $ajouter->setQuantite(1);
            $ajouter->setProduit($produit);
            $ajouter->setPanier($panier);
        }

        $em->persist($u);
        $em->persist($produit);
        $em->persist($panier);
        $em->flush();

        return $this->redirect('$referer' ?? $this->generateUrl('app_accueil'));
    }

}
