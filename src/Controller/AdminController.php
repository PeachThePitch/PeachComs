<?php
namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Produit;
use App\Entity\User;
use App\Repository\AvisRepository;
use App\Service\UploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    // ── Dashboard ──
    #[Route('', name: 'app_admin')]
    public function index(EntityManagerInterface $em): Response
    {
        $commandes = $em->getRepository(Commande::class)->findAll();
    
        // Revenus total
        $revenusTotal = array_sum(array_map(fn($c) => $c->getTotal(), $commandes));
    
        // Revenus du mois en cours
        $moisActuel = (new \DateTime())->format('Y-m');
        $revenusMois = array_sum(array_map(
            fn($c) => $c->getDateCommande()->format('Y-m') === $moisActuel ? $c->getTotal() : 0,
            $commandes
        ));
    
        // Commandes du mois
        $commandesMois = count(array_filter(
            $commandes,
            fn($c) => $c->getDateCommande()->format('Y-m') === $moisActuel
        ));
    
        return $this->render('admin/dashboard.html.twig', [
            'nbCommandes'   => count($commandes),
            'nbProduits'    => $em->getRepository(Produit::class)->count([]),
            'nbUsers'       => $em->getRepository(User::class)->count([]),
            'commandes'     => $em->getRepository(Commande::class)->findBy([], ['dateCommande' => 'DESC']),
            'revenusTotal'  => $revenusTotal,
            'revenusMois'   => $revenusMois,
            'commandesMois' => $commandesMois,
        ]);
    }

    // ── Commandes ──
    #[Route('/commandes', name: 'app_admin_commandes')]
    public function commandes(EntityManagerInterface $em): Response
    {
        return $this->render('admin/commandes.html.twig', [
            'commandes' => $em->getRepository(Commande::class)->findBy([], ['dateCommande' => 'DESC']),
        ]);
    }

    #[Route('/commande/{id}/etat', name: 'app_admin_commande_etat', methods: ['POST'])]
    public function changerEtat(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $etat = $request->request->get('etat');
        if (in_array($etat, ['En attente', 'En cours', 'Terminée'])) {
            $commande->setEtat($etat);
            $em->flush();
        }
        return $this->redirectToRoute('app_admin_commandes');
    }

    #[Route('/commande/{id}/supprimer', name: 'app_admin_commande_supprimer', methods: ['POST'])]
    public function supprimerCommande(Commande $commande, EntityManagerInterface $em): Response
    {
        // Supprime les messages liés
        foreach ($commande->getMessages() as $message) {
            $em->remove($message);
        }

        $em->remove($commande);
        $em->flush();

        return $this->redirectToRoute('app_admin_commandes');
    }

    // ── Chats ──
    #[Route('/chats', name: 'app_admin_chats')]
    public function chats(EntityManagerInterface $em): Response
    {
        return $this->render('admin/chats.html.twig', [
            'commandes' => $em->getRepository(Commande::class)->findBy([], ['dateCommande' => 'DESC']),
        ]);
    }

    #[Route('/chat/{id}', name: 'app_admin_chat')]
    public function chat(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $contenu = $request->request->get('contenu');
            $fichier = $request->files->get('image');

            if ($contenu || $fichier) {
                $message = new \App\Entity\Message();
                $message->setContenu($contenu ?? '');
                $message->setDateEnvoi(new \DateTime());
                $message->setUser($this->getUser());
                $message->setCommande($commande);
                $message->setLu(false);

                if ($fichier) {
                    $nomFichier = uniqid() . '.' . $fichier->guessExtension();
                    $fichier->move(
                        $this->getParameter('kernel.project_dir') . '/public/images/chat/',
                        $nomFichier
                    );
                    $message->setImage($nomFichier);
                }

                $em->persist($message);
            }
            $em->flush();

            return $this->redirectToRoute('app_admin_chat', ['id' => $commande->getId()]);
        }

        // Marquer comme lus les messages du client
        foreach ($commande->getMessages() as $message) {
            if ($message->getUser() !== $this->getUser() && !$message->isLu()) {
                $message->setLu(true); // L'admin lit son propre message
            }
        }
        $em->flush();

        $messages = $commande->getMessages()->toArray();
        usort($messages, fn($a, $b) => $a->getDateEnvoi() <=> $b->getDateEnvoi());

        return $this->render('admin/chat.html.twig', [
            'commande' => $commande,
            'messages' => $messages,
        ]);
    }

    // ── Inscrits ──
    #[Route('/inscrits', name: 'app_admin_inscrits')]
    public function inscrits(EntityManagerInterface $em): Response
    {
        return $this->render('admin/inscrits.html.twig', [
            'users' => $em->getRepository(User::class)->findAll(),
        ]);
    }

    #[Route('/inscrits/{id}/role', name: 'app_admin_inscrit_role', methods: ['POST'])]
    public function changerRole(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $role = $request->request->get('role');
        if (in_array($role, ['ROLE_USER', 'ROLE_ADMIN'])) {
            $user->setRoles([$role]);
            $em->flush();
        }
        return $this->redirectToRoute('app_admin_inscrits');
    }

    #[Route('/inscrits/{id}/supprimer', name: 'app_admin_inscrit_supprimer', methods: ['POST'])]
    public function supprimerInscrit(User $user, EntityManagerInterface $em): Response
    {
        // Empêcher de se supprimer soi-même
        if ($user === $this->getUser()) {
            return $this->redirectToRoute('app_admin_inscrits');
        }

        $em->remove($user);
        $em->flush();

        return $this->redirectToRoute('app_admin_inscrits');
    }

    // ── Ajouter un produit ──
    #[Route('/produits/ajouter', name: 'app_admin_produit_ajouter', methods: ['GET', 'POST'])]
    public function ajouterProduit(Request $request, EntityManagerInterface $em, UploadService $uploadService): Response
    {
        if ($request->isMethod('POST')) {

            $fichier = $request->files->get('image');

            // Validation image de couverture
            $erreur = $uploadService->valider($fichier);
            if ($erreur) {
                return $this->render('admin/produit_form.html.twig', [
                    'produit' => null,
                    'erreur' => $erreur,
                ]);
            }

            $nomFichier = $uploadService->upload(
                $fichier,
                $this->getParameter('kernel.project_dir') . '/public/images/'
            );

            $produit = new Produit();
            $produit->setDesignation($request->request->get('designation'));
            $produit->setPrix((float) $request->request->get('prix'));
            $produit->setDescription($request->request->get('description'));
            $produit->setImage($nomFichier);
            $produit->setType($request->request->get('type') ?: null);
            $produit->setStyle($request->request->get('style') ?: null);

            $slots = $request->request->get('slots');
            $produit->setSlots($slots !== '' && $slots !== null ? (int) $slots : null);

            // Photos d'exemples
            $photos = [];
            foreach ($request->files->get('photos', []) as $photo) {
                $erreur = $uploadService->valider($photo);
                if ($erreur) {
                    return $this->render('admin/produit_form.html.twig', [
                        'produit' => null,
                        'erreur' => $erreur,
                    ]);
                }
                $photos[] = $uploadService->upload(
                    $photo,
                    $this->getParameter('kernel.project_dir') . '/public/images/exemples/'
                );
            }
            $produit->setPhotos($photos);

            $em->persist($produit);
            $em->flush();

            return $this->redirectToRoute('app_admin_produits');
        }

        return $this->render('admin/produit_form.html.twig', [
            'produit' => null,
        ]);
    }

    // ── Produits ──
    #[Route('/produits', name: 'app_admin_produits')]
    public function produits(EntityManagerInterface $em): Response
    {
        return $this->render('admin/produits.html.twig', [
            'produits' => $em->getRepository(Produit::class)->findAll(),
        ]);
    }

// ── Modifier un produit ──
    #[Route('/produits/{id}/modifier', name: 'app_admin_produit_modifier', methods: ['GET', 'POST'])]
    public function modifierProduit(Produit $produit, Request $request, EntityManagerInterface $em, UploadService $uploadService): Response
    {
        if ($request->isMethod('POST')) {
            $produit->setDesignation($request->request->get('designation'));
            $produit->setPrix((float) $request->request->get('prix'));
            $produit->setDescription($request->request->get('description'));
            $produit->setType($request->request->get('type') ?: null);
            $produit->setStyle($request->request->get('style') ?: null);

            $slots = $request->request->get('slots');
            $produit->setSlots($slots !== '' && $slots !== null ? (int) $slots : null);

            // Image de couverture
            $fichier = $request->files->get('image');
            if ($fichier) {
                $erreur = $uploadService->valider($fichier);
                if ($erreur) {
                    return $this->render('admin/produit_form.html.twig', [
                        'produit' => $produit,
                        'erreur' => $erreur,
                    ]);
                }
                $nomFichier = $uploadService->upload(
                    $fichier,
                    $this->getParameter('kernel.project_dir') . '/public/images/'
                );
                $produit->setImage($nomFichier);
            }

            // Nouvelles photos d'exemples
            $nouvellesPhotos = [];
            foreach ($request->files->get('photos', []) as $photo) {
                $erreur = $uploadService->valider($photo);
                if ($erreur) {
                    return $this->render('admin/produit_form.html.twig', [
                        'produit' => $produit,
                        'erreur' => $erreur,
                    ]);
                }
                $nouvellesPhotos[] = $uploadService->upload(
                    $photo,
                    $this->getParameter('kernel.project_dir') . '/public/images/exemples/'
                );
            }

            // On fusionne avec les anciennes
            $photos = array_merge($produit->getPhotos() ?? [], $nouvellesPhotos);
            $produit->setPhotos($photos);

            $em->flush();

            return $this->redirectToRoute('app_admin_produits');
        }

        return $this->render('admin/produit_form.html.twig', [
            'produit' => $produit,
        ]);
    }
// ── Supprimer un produit ──
    #[Route('/produits/{id}/supprimer', name: 'app_admin_produit_supprimer', methods: ['POST'])]
    public function supprimerProduit(Produit $produit, EntityManagerInterface $em): Response
    {
        $em->remove($produit);
        $em->flush();

        return $this->redirectToRoute('app_admin_produits');
    }

    #[Route('/produits/{id}/photo/{photo}', name: 'app_admin_produit_photo_supprimer', methods: ['GET'])]
    public function supprimerPhoto(Produit $produit, string $photo, EntityManagerInterface $em): Response
    {
        $photos = $produit->getPhotos() ?? [];
        $photos = array_filter($photos, fn($p) => $p !== $photo);
        $produit->setPhotos(array_values($photos));
        $em->flush();

        // Supprimer le fichier physique
        $chemin = $this->getParameter('kernel.project_dir') . '/public/images/exemples/' . $photo;
        if (file_exists($chemin)) {
            unlink($chemin);
        }

        return $this->redirectToRoute('app_admin_produit_modifier', ['id' => $produit->getId()]);
    }

    #[Route('/avis', name: 'app_admin_avis')]
    public function avis(AvisRepository $avisRepository): Response
    {
        return $this->render('admin/avis.html.twig', [
            'avis' => $avisRepository->findBy([], ['dateAvis' => 'DESC']),
        ]);
    }

    #[Route('/avis/{id}/valider', name: 'app_admin_avis_valider', methods: ['POST'])]
    public function validerAvis(Avis $avis, EntityManagerInterface $em): Response
    {
        $avis->setValide(!$avis->isValide());
        $em->flush();
        return $this->redirectToRoute('app_admin_avis');
    }

    #[Route('/avis/{id}/supprimer', name: 'app_admin_avis_supprimer', methods: ['POST'])]
    public function supprimerAvis(Avis $avis, EntityManagerInterface $em): Response
    {
        if ($avis->getImage()) {
            $chemin = $this->getParameter('kernel.project_dir') . '/public/images/avis/' . $avis->getImage();
            if (file_exists($chemin)) {
                unlink($chemin);
            }

        }
        $em->remove($avis);
        $em->flush();
        return $this->redirectToRoute('app_admin_avis');
    }
}
