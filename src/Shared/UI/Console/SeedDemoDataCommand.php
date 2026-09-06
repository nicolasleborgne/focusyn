<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use App\Assistant\Application\Port\KeyVault;
use App\Assistant\Domain\Model\AssistantSettings;
use App\Assistant\Domain\Model\OwnerId;
use App\Assistant\Domain\Repository\AssistantSettingsRepository;
use App\Identity\Application\Command\RegisterUser\RegisterUser;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Inbox\Domain\Model\Capture;
use App\Inbox\Domain\Model\CaptureId;
use App\Inbox\Domain\Model\CaptureSource;
use App\Inbox\Domain\Repository\CaptureRepository;
use App\Notebook\Domain\Model\AuthorId;
use App\Notebook\Domain\Model\Note;
use App\Notebook\Domain\Model\NoteBody;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\NoteTitle;
use App\Notebook\Domain\Model\Obsession;
use App\Notebook\Domain\Model\ObsessionBlurb;
use App\Notebook\Domain\Model\ObsessionId;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Model\ObsessionPoint;
use App\Notebook\Domain\Repository\NoteRepository;
use App\Notebook\Domain\Repository\ObsessionRepository;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Model\PrivacyChoices;
use App\Privacy\Domain\Model\SubjectId;
use App\Privacy\Domain\Repository\PrivacyChoicesRepository;
use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Model\Reminder;
use App\Reminder\Domain\Model\ReminderId;
use App\Reminder\Domain\Model\ReminderLabel;
use App\Reminder\Domain\Model\ReminderSubject;
use App\Reminder\Domain\Repository\ReminderRepository;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Tenant\TenantScope;
use App\Shared\Domain\TenantId;
use App\Task\Domain\Model\TaskItemId;
use App\Task\Domain\Model\TaskList;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Model\TaskListName;
use App\Task\Domain\Model\TaskText;
use App\Task\Domain\Repository\TaskListRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplit un compte de démonstration avec le contenu de la maquette.
 *
 * Sert à juger le rendu sur des données réalistes — des titres de vraie
 * longueur, des extraits qui se tronquent, des listes à moitié cochées — plutôt
 * que sur des écrans vides. Réservée au développement.
 *
 * Les agrégats sont construits directement plutôt que par les cas d'usage :
 * ceux-ci lisent l'organisation et l'auteur dans le contexte de la requête, qui
 * n'existe pas en console.
 */
#[AsCommand(name: 'app:demo', description: 'Sème un compte de démonstration avec le contenu de la maquette')]
final class SeedDemoDataCommand extends Command
{
    private const string EMAIL = 'demo@focusyn.fr';
    private const string PASSWORD = 'une phrase de passe tenable';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly UserRepository $users,
        private readonly OrganizationRepository $organizations,
        private readonly NoteRepository $notes,
        private readonly ObsessionRepository $obsessions,
        private readonly TaskListRepository $lists,
        private readonly ReminderRepository $reminders,
        private readonly CaptureRepository $captures,
        private readonly PrivacyChoicesRepository $privacy,
        private readonly AssistantSettingsRepository $assistant,
        private readonly KeyVault $vault,
        private readonly TenantScope $scope,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = EmailAddress::fromString(self::EMAIL);
        $user = $this->users->ofEmail($email);

        if (null === $user) {
            $this->commands->dispatch(new RegisterUser(self::EMAIL, self::PASSWORD));
            $user = $this->users->ofEmail($email);
        }

        if (null === $user) {
            $io->error('Le compte de démonstration n\'a pas pu être créé.');

            return Command::FAILURE;
        }

        $organizations = $this->organizations->ofMember(MemberId::fromString($user->id()->toString()));
        $tenant = TenantId::fromString($organizations[0]->id()->toString());
        $author = AuthorId::fromString($user->id()->toString());

        // Le cloisonnement n'est pas armé en console : on le déclare
        // explicitement, faute de quoi la purge toucherait toutes les
        // organisations de la base.
        $recipient = RecipientId::fromString($user->id()->toString());

        $this->scope->runAs($tenant, function () use ($tenant, $author, $recipient): void {
            $this->purge();
            $this->seedNotes($tenant, $author);
            $this->seedObsessions($tenant);
            $this->seedLists($tenant);
            $this->seedReminders($tenant, $recipient);
            $this->seedInbox($tenant);
            $this->seedAssistant($recipient);
        });

        $io->success(\sprintf('Compte de démonstration prêt : %s / %s', self::EMAIL, self::PASSWORD));

        return Command::SUCCESS;
    }

    /**
     * Rejouer la commande doit redonner exactement le même carnet, pas
     * l'empiler sur le précédent.
     */
    private function purge(): void
    {
        foreach ($this->notes->mostRecent(500) as $note) {
            $this->notes->remove($note);
        }

        foreach ($this->lists->all() as $list) {
            $this->lists->remove($list);
        }

        foreach ($this->reminders->all() as $reminder) {
            $this->reminders->remove($reminder);
        }

        foreach ($this->captures->pending() as $capture) {
            $this->captures->remove($capture);
        }

        foreach (self::obsessions() as [$name]) {
            $existing = $this->obsessions->ofSlug(ObsessionName::fromString($name)->slug());

            if (null !== $existing) {
                $this->obsessions->remove($existing);
            }
        }
    }

    /**
     * Deux entrées dans la boîte, de provenances différentes : une adresse
     * partagée depuis un navigateur, et une phrase collée à la main. C'est ce
     * qui distingue les deux étiquettes à l'écran.
     */
    private function seedInbox(TenantId $tenant): void
    {
        $captures = [
            ["Segmented sleep in pre-industrial Europe\n\nhttps://exemple.fr/ekirch", CaptureSource::Shared],
            ["L'agitation compte autant que le temps de contact\n\nRemuer à mi-parcours change l'extraction plus que trente secondes de plus.", CaptureSource::TypedIn],
        ];

        foreach ($captures as $index => [$text, $source]) {
            $this->captures->save(Capture::receive(
                CaptureId::generate(),
                $tenant,
                $text,
                $source,
                $this->clock->now()->modify(\sprintf('-%d hours', 2 * ($index + 1))),
            ));
        }
    }

    /**
     * Une échéance sur la première note et sur la première tâche : de quoi voir
     * les deux états de la pastille sur une capture.
     */
    private function seedReminders(TenantId $tenant, RecipientId $recipient): void
    {
        $now = $this->clock->now();
        $notes = $this->notes->mostRecent(1);
        $lists = $this->lists->all();
        $subjects = [];

        if ([] !== $notes) {
            $subjects[] = [ReminderSubject::note($notes[0]->id()->toString()), $notes[0]->title()->toString()];
        }

        if ([] !== $lists && [] !== $lists[0]->items()) {
            $item = $lists[0]->items()[0];
            $subjects[] = [ReminderSubject::task($item->id()->toString()), $item->text()->toString()];
        }

        foreach ($subjects as $index => [$subject, $label]) {
            $this->reminders->save(Reminder::schedule(
                ReminderId::generate(),
                $tenant,
                $recipient,
                $subject,
                ReminderLabel::fromString($label),
                $now->modify(\sprintf('+%d days', $index + 2))->setTime(9, 0),
                $now,
            ));
        }
    }

    /**
     * L'assistant consenti et branché : sans cela, ni le panneau de l'éditeur
     * ni l'état « enregistrée » des réglages ne se voient sur une capture.
     *
     * La clé est factice — l'écran ne la relit jamais, et rien n'appelle le
     * fournisseur tant qu'on ne clique pas.
     */
    private function seedAssistant(RecipientId $person): void
    {
        $subject = SubjectId::fromString($person->toString());
        $choices = $this->privacy->ofSubject($subject) ?? PrivacyChoices::forSubject($subject, $this->clock->now());
        $choices->grant(Consent::Assistant, $this->clock->now());
        $this->privacy->save($choices);

        $owner = OwnerId::fromString($person->toString());
        $settings = $this->assistant->ofOwner($owner) ?? AssistantSettings::forOwner($owner);
        $settings->useKey($this->vault->seal('sk-ant-demonstration-sans-valeur'));
        $this->assistant->save($settings);
    }

    private function seedNotes(TenantId $tenant, AuthorId $author): void
    {
        $now = $this->clock->now();

        foreach (self::notes() as $index => [$title, $obsessions, $body]) {
            $this->notes->save(Note::write(
                NoteId::generate(),
                $tenant,
                $author,
                NoteTitle::fromString($title),
                NoteBody::fromString($body),
                array_map(ObsessionName::fromString(...), $obsessions),
                $now->modify(\sprintf('-%d hours', ($index + 1) * 7)),
            ));
        }
    }

    private function seedObsessions(TenantId $tenant): void
    {
        $now = $this->clock->now();

        foreach (self::obsessions() as [$name, $blurb, $points]) {
            $obsession = Obsession::describe(
                ObsessionId::generate(),
                $tenant,
                ObsessionName::fromString($name),
                ObsessionBlurb::fromString($blurb),
                array_map(ObsessionPoint::fromString(...), $points),
                $now,
            );

            $this->obsessions->save($obsession);
        }
    }

    private function seedLists(TenantId $tenant): void
    {
        $now = $this->clock->now();

        foreach (self::lists() as $index => [$name, $tasks]) {
            $list = TaskList::open(
                TaskListId::generate(),
                $tenant,
                TaskListName::fromString($name),
                $now->modify(\sprintf('-%d days', $index + 1)),
            );

            foreach ($tasks as [$text, $done]) {
                $taskId = TaskItemId::generate();
                $list->addTask($taskId, TaskText::fromString($text), $now);

                if ($done) {
                    $list->toggleTask($taskId, $now);
                }
            }

            $this->lists->save($list);
        }
    }

    /** @return list<array{string, list<string>, string}> */
    private static function notes(): array
    {
        return [
            [
                "Le sommeil biphasique n'est pas une invention moderne",
                ['Sommeil', 'Histoire'],
                <<<'MD'
                    # Deux sommeils

                    Avant l'éclairage artificiel, la nuit se coupait en deux. Un *premier sommeil*, une veille d'une heure ou deux, puis un **second sommeil**. Roger Ekirch a retrouvé la trace de cette veille dans des centaines de sources : prières, lettres, procès-verbaux.

                    > La veille du milieu de la nuit n'était pas une insomnie. C'était un moment social.

                    ## Ce que ça change pour moi

                    - Se réveiller à 3 h n'est pas forcément un symptôme
                    - Le stress vient de l'interprétation, pas du réveil
                    - La lumière est la variable, pas la volonté

                    À tester : deux semaines sans écran après 21 h, et noter l'heure du réveil spontané.

                    ---

                    ### À relire

                    [At Day's Close](https://exemple.fr/ekirch) — chapitre 8. Et croiser avec la note sur le `carnet de 3 mois`.
                    MD,
            ],
            [
                "Extraction : pourquoi 1:16 n'est pas une loi",
                ['Café'],
                <<<'MD'
                    # Le ratio n'est qu'une variable

                    Le **ratio** ne décide de rien tout seul. Ce qui compte, c'est le trio *mouture · temps · agitation*.

                    ## Trois essais

                    - 1:15, mouture moyenne, 3 min — plat
                    - 1:16, mouture fine, 4 min — net, acidité tenue
                    - 1:17, mouture fine, 5 min — trop bavard

                    > Le chiffre rond rassure. Il ne goûte rien.

                    Prochaine étape : fixer la mouture et ne bouger que le temps.
                    MD,
            ],
            [
                'Grille suisse : la marge est un argument',
                ['Typographie'],
                <<<'MD'
                    # La marge parle

                    Chez Müller-Brockmann, le blanc n'est pas du vide : c'est la **structure rendue visible**.

                    - Une grille de 6 colonnes suffit presque toujours
                    - La hiérarchie vient du poids, pas de la taille
                    - Deux graisses, une famille

                    > Réduire jusqu'à ce que retirer casse quelque chose.
                    MD,
            ],
            [
                'Palais de mémoire — protocole en 20 minutes',
                ['Mémoire'],
                <<<'MD'
                    # Protocole court

                    1. Choisir un lieu **connu par cœur**
                    2. Fixer 10 stations dans un ordre non ambigu
                    3. Y déposer une image absurde par élément

                    > L'absurde tient mieux que le logique.

                    Rappel à J+1, J+3, J+7.
                    MD,
            ],
            [
                'Acier vs carbone : ce que dit vraiment la fatigue',
                ['Vélo'],
                <<<'MD'
                    # Fatigue et seuil

                    L'acier a une **limite d'endurance** : sous un certain seuil de contrainte, il ne se fatigue pas. Le carbone n'en a pas au même sens, mais les contraintes réelles d'un cadre sont très en dessous.

                    - Le confort vient de la géométrie avant le matériau
                    - La réparabilité, elle, vient bien du matériau
                    MD,
            ],
            [
                'Levain : la courbe de pH comme journal de bord',
                ['Fermentation'],
                <<<'MD'
                    # Mesurer au lieu de deviner

                    Le pH descend de 6,0 à 4,2 pendant la fermentation. Noter l'heure à chaque demi-point transforme une intuition en **courbe**.

                    - 4,6 : le goût bascule
                    - Sous 4,0 : acétique, trop tard
                    MD,
            ],
            [
                'Lire moins, relire mieux',
                ['Lecture', 'Mémoire'],
                <<<'MD'
                    # Relecture espacée

                    Une relecture *active* à J+7 vaut trois lectures d'affilée.

                    > Ce qui n'a pas été reformulé n'a pas été lu.
                    MD,
            ],
            [
                'Carnet : 3 mois de sommeil segmenté',
                ['Sommeil'],
                <<<'MD'
                    # Journal

                    - Semaine 1-2 : réveil vers 3 h 10, rendormissement long
                    - Semaine 5 : réveil accepté, lecture 25 min
                    - Semaine 11 : fenêtre stable, humeur meilleure

                    > Le changement n'est pas le sommeil, c'est l'anxiété autour.
                    MD,
            ],
        ];
    }

    /** @return list<array{string, string, list<string>}> */
    private static function obsessions(): array
    {
        return [
            ['Sommeil', 'Segmentation, lumière, anxiété nocturne.', [
                'La veille nocturne est un fait historique, pas un trouble.',
                'La lumière artificielle est la variable qui a soudé la nuit en un bloc.',
                'Sur douze semaines, c\'est l\'anxiété qui bouge — pas la durée.',
            ]],
            ['Café', 'Extraction, mouture, ce que le ratio ne dit pas.', [
                'Le ratio est un repère, pas une cause.',
                'Fixer la mouture avant de toucher au temps.',
                'Le goût plat signale une sous-extraction, pas un mauvais grain.',
            ]],
            ['Typographie', 'Grille suisse, marges, hiérarchie par le poids.', [
                'Le blanc est structurel.',
                'Deux graisses suffisent si la grille est juste.',
                'Retirer jusqu\'à ce que ça casse.',
            ]],
            ['Mémoire', 'Palais, espacement, reformulation.', [
                'L\'ordre du lieu porte l\'ordre du contenu.',
                'L\'image absurde survit à l\'image logique.',
                'Sans reformulation, pas de trace.',
            ]],
            ['Vélo', 'Acier, fatigue, géométrie du confort.', [
                'Le matériau décide de la réparation, la géométrie du confort.',
                'Les contraintes réelles restent sous le seuil de fatigue.',
            ]],
            ['Fermentation', 'pH, temps, journal de bord.', [
                'Une mesure vaut dix impressions.',
                '4,6 de pH : le point de bascule du goût.',
            ]],
            ['Histoire', 'Sources anciennes des habitudes actuelles.', [
                'Beaucoup d\'habitudes « naturelles » ont une date.',
            ]],
            ['Lecture', 'Relire activement plutôt que lire beaucoup.', [
                'Le nombre de livres est une mauvaise métrique.',
            ]],
        ];
    }

    /** @return list<array{string, list<array{string, bool}>}> */
    private static function lists(): array
    {
        return [
            ['Cette semaine', [
                ['Écrire la synthèse Sommeil', false],
                ['Trier les 6 notes en friche', false],
                ['Sortir le vélo, 40 km', true],
            ]],
            ['Protocole sommeil', [
                ['Deux semaines sans écran après 21 h', false],
                ["Noter l'heure du réveil spontané chaque matin", false],
                ['Relire Ekirch, chapitre 8', true],
                ['Fusionner le carnet de 12 semaines', false],
            ]],
            ['Essais café', [
                ['Mouture fixe, temps variable : 3 / 4 / 5 min', false],
                ['Peser la dose au 0,1 g pendant une semaine', true],
                ["Refaire l'essai 1:17 avec agitation réduite", false],
            ]],
            ['Matériel à acheter', [
                ['pH-mètre à sonde', false],
                ['Balance 0,1 g', true],
                ['Pied à coulisse pour la géométrie du cadre', false],
            ]],
            ['À lire / relire', [
                ['Müller-Brockmann — Systèmes de grilles', false],
                ['Relecture active J+7 de la note Mémoire', false],
            ]],
        ];
    }
}
