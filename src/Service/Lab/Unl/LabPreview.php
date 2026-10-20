<?php

namespace App\Service\Lab\Unl;

use JsonSerializable;

/**
 * Resultat normalise de l'analyse d'un fichier EVE-NG (.unl ou .zip).
 *
 * Un LabPreview transporte :
 *  - `source`  : la provenance du fichier (format, nom, entree dans le zip) ;
 *  - `lab`     : les metadonnees du lab (nom, compteurs) ;
 *  - `devices` : la liste des devices convertis (pour l'affichage) ;
 *  - `images`  : l'etat de resolution de chaque image (statut + OS propose) ;
 *  - `labJson` : le JSON pret a etre passe a LabImporter::import() lors du commit ;
 *  - `warnings`: messages non bloquants a remonter a l'utilisateur.
 *
 * La classe est volontairement une simple enveloppe serialisable : le commit
 * consomme `labJson`, tandis que l'IHM consomme le reste.
 */
class LabPreview implements JsonSerializable
{
    private array $source;
    private array $lab;
    private array $devices;
    private array $images;
    private array $warnings;
    private array $labJson;

    public function __construct(
        array $source,
        array $lab,
        array $devices,
        array $images,
        array $labJson,
        array $warnings = []
    ) {
        $this->source = $source;
        $this->lab = $lab;
        $this->devices = $devices;
        $this->images = $images;
        $this->labJson = $labJson;
        $this->warnings = $warnings;
    }

    public function getSource(): array
    {
        return $this->source;
    }

    public function getLab(): array
    {
        return $this->lab;
    }

    public function getDevices(): array
    {
        return $this->devices;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Le JSON "Lab RemoteLabz" (format attendu par LabImporter::import()).
     */
    public function getLabJson(): array
    {
        return $this->labJson;
    }

    /**
     * Vue publique (sans le labJson, inutile et potentiellement volumineux cote
     * client : le commit est recalcule cote serveur).
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'lab' => $this->lab,
            'devices' => $this->devices,
            'images' => $this->images,
            'warnings' => $this->warnings,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
