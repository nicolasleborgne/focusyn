<?php

declare(strict_types=1);

namespace App\Assistant\Application\Query;

/**
 * Les cinq demandes de la maquette.
 *
 * Les consignes vivent côté serveur et non dans le gabarit : un client ne doit
 * pas pouvoir choisir ce qu'on dit au modèle en son nom.
 */
enum QuickAction: string
{
    case Summarise = 'summarise';
    case Clarify = 'clarify';
    case Leads = 'leads';
    case Tensions = 'tensions';
    case Tasks = 'tasks';

    public function instruction(): string
    {
        return match ($this) {
            self::Summarise => 'Résume cette note en 3 puces markdown maximum.',
            self::Clarify => 'Réécris cette note plus clairement, même structure markdown, sans rien ajouter.',
            self::Leads => 'Propose 3 pistes à creuser, en puces markdown, sous un titre de niveau 2.',
            self::Tensions => 'Relève les tensions ou contradictions de cette note en 2 ou 3 puces markdown.',
            self::Tasks => 'Extrais de cette note 3 tâches concrètes, en puces markdown commençant par un verbe.',
        };
    }
}
