<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'app_profil')]
    public function index(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $u = $this->getUser();
        $erreur = null;
        $succes = null;

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            if ($action === 'infos') {
                $u->setPseudo($request->request->get('pseudo'));
                $u->setNom($request->request->get('nom'));
                $u->setPrenom($request->request->get('prenom'));
                $u->setEmail($request->request->get('email'));
                $em->flush();
                $succes = 'Informations mises à jour !';

            } elseif ($action === 'password') {
                $ancienMdp = $request->request->get('ancien_mdp');
                $nouveauMdp = $request->request->get('nouveau_mdp');
                $confirmMdp = $request->request->get('confirm_mdp');

                if (!$hasher->isPasswordValid($u, $ancienMdp)) {
                    $erreur = 'Ancien mot de passe incorrect.';
                } elseif ($nouveauMdp !== $confirmMdp) {
                    $erreur = 'Les nouveaux mots de passe ne correspondent pas.';
                } elseif (strlen($nouveauMdp) < 6) {
                    $erreur = 'Le mot de passe doit faire au moins 6 caractères.';
                } else {
                    $u->setPassword($hasher->hashPassword($u, $nouveauMdp));
                    $em->flush();
                    $succes = 'Mot de passe mis à jour !';
                }
            }
        }

        return $this->render('profil/index.html.twig', [
            'erreur' => $erreur,
            'succes' => $succes,
        ]);
    }
}