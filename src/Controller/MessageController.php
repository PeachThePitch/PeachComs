<?php

namespace App\Controller;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Message;


class MessageController extends AbstractController
{
    #[Route('/commande/{id}/chat', name: 'app_chat')]
    public function chat(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($commande->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $contenu = $request->request->get('contenu');
            $fichier = $request->files->get('image');

            if ($contenu || $fichier) {
                $message = new Message();
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

            return $this->redirectToRoute('app_chat', ['id' => $commande->getId()]);
        }

        // Marquer comme lus tous les messages envoyés par l'artiste (pas par le client)
        foreach ($commande->getMessages() as $message) {
            $em->refresh($message);
            if ($message->getUser() !== $this->getUser() && !$message->isLu()) {
                $message->setLu(true);
            }
        }
        $em->flush();

        $messages = $commande->getMessages()->toArray();
        usort($messages, fn($a, $b) => $a->getDateEnvoi() <=> $b->getDateEnvoi());

        return $this->render('message/chat.html.twig', [
            'commande' => $commande,
            'messages' => $messages,
        ]);
    }

}
