<?php

namespace App\Controller;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommandeController extends AbstractController
{
    #[Route('/commandes', name: 'app_commandes')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $commandes = $this->getUser()->getCommandes()->toArray();

        // Du plus récent au plus ancien
        usort($commandes, fn($a, $b) => $b->getDateCommande() <=> $a->getDateCommande());

        return $this->render('commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/commande/passer', name: 'app_commande_passer')]
    public function passer(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $u = $this->getUser();
        $panier = $u->getPanier();

        if (!$panier || $panier->getajouters()->isEmpty()) {
            return $this->redirectToRoute('app_panier');
        }

        // Calcul du total TTC
        $total = 0;
        foreach ($panier->getajouters() as $ligne) {
            $total += $ligne->getProduit()->getPrix() * $ligne->getQuantite();
        }
        $total = $total * 1.20; // TVA 20%

        // Créer la commande
        $commande = new Commande();
        $commande->setDateCommande(new \DateTime());
        $commande->setEtat('En attente');
        $commande->setTotal(round($total, 2));
        $commande->setUser($u);

        $em->persist($commande);

        // Vider le panier
        foreach ($panier->getajouters() as $ligne) {
            $em->remove($ligne);
        }

        // Décrémenter les slots des produits commandés
        foreach ($panier->getAjouters() as $ligne) {
            $produit = $ligne->getProduit();
            if ($produit->getSlots() !== null && $produit->getSlots() > 0) {
                $produit->setSlots($produit->getSlots() - $ligne->getQuantite());
                // S'assurer que les slots ne passent pas en négatif
                if ($produit->getSlots() < 0) {
                    $produit->setSlots(0);
                }
            }
        }

        $em->flush();

        return $this->redirectToRoute('app_commandes');
    }
}
