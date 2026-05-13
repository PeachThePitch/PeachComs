<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadService
{
    private const TYPES_AUTORISES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    private const TAILLE_MAX = 8 * 1024 * 1024; // 8 Mo

    public function valider(UploadedFile $fichier): ?string
    {
        $extension = strtolower($fichier->getClientOriginalExtension());
        $extensionsAutorisees = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($extension, $extensionsAutorisees)) {
            return 'Format non supporté. Utilise un fichier JPG, JPEG, PNG ou WEBP.';
        }

        if ($fichier->getSize() > self::TAILLE_MAX) {
            return 'Le fichier est trop lourd (8 Mo maximum).';
        }

        return null;
    }

    public function upload(UploadedFile $fichier, string $dossier): string
    {
        $extension = strtolower($fichier->getClientOriginalExtension());
        $nomFichier = uniqid() . '.' . $extension;
        $fichier->move($dossier, $nomFichier);
        return $nomFichier;
    }
}
