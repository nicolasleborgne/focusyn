<?php

declare(strict_types=1);

namespace App\Tests\Integration\Reminder;

use App\Identity\Application\Command\RegisterUser\RegisterUser;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Reminder\Application\Command\NotifyDueReminders\NotifyDueReminders;
use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Repository\ReminderRepository;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\TenantId;
use App\Tests\Factory\Reminder\ReminderFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le tour de garde du worker.
 *
 * Il tourne hors requête HTTP, donc sans cloisonnement armé : c'est voulu, et
 * ces tests le vérifient en mêlant deux organisations.
 */
final class NotifyDueRemindersTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    public function testWhatIsDueGoesOutAndIsNeverSentTwice(): void
    {
        self::mockTime('2026-09-10 09:05:00');
        $recipient = $this->registerAccount('nicolas@focusyn.fr');

        ReminderFactory::new()->forRecipient($recipient)->about('Relire la synthèse')->dueAt('2026-09-10 09:00')->create();

        self::assertSame(1, $this->watch());
        self::assertEmailCount(1);

        // Second tour : plus rien à envoyer.
        self::assertSame(0, $this->watch());
    }

    public function testWhatIsNotDueYetStaysPut(): void
    {
        self::mockTime('2026-09-10 08:59:00');
        $recipient = $this->registerAccount('nicolas@focusyn.fr');

        ReminderFactory::new()->forRecipient($recipient)->dueAt('2026-09-10 09:00')->create();

        self::assertSame(0, $this->watch());
        self::assertEmailCount(0);
    }

    public function testALateReminderIsSentLateRatherThanNever(): void
    {
        // Le worker s'est tu pendant deux jours ; ce qui a été manqué part
        // quand même.
        self::mockTime('2026-09-12 09:00:00');
        $recipient = $this->registerAccount('nicolas@focusyn.fr');

        ReminderFactory::new()->forRecipient($recipient)->dueAt('2026-09-10 09:00')->create();

        self::assertSame(1, $this->watch());
    }

    public function testTheWorkerSeesEveryOrganization(): void
    {
        self::mockTime('2026-09-10 09:05:00');
        $alice = $this->registerAccount('alice@focusyn.fr');
        $bob = $this->registerAccount('bob@focusyn.fr');

        ReminderFactory::new()->ownedBy(TenantId::generate())->forRecipient($alice)->dueAt('2026-09-10 09:00')->create();
        ReminderFactory::new()->ownedBy(TenantId::generate())->forRecipient($bob)->dueAt('2026-09-10 09:00')->create();

        self::assertSame(2, $this->watch());
        self::assertEmailCount(2);
    }

    public function testAReminderWhoseAccountIsGoneIsRetiredRatherThanRetriedForever(): void
    {
        self::mockTime('2026-09-10 09:05:00');

        ReminderFactory::new()->forRecipient(RecipientId::generate())->dueAt('2026-09-10 09:00')->create();

        self::assertSame(0, $this->watch(), 'Personne à prévenir : rien ne part.');
        self::assertEmailCount(0);

        // Mais il ne doit pas revenir au tour suivant.
        $reminders = self::getContainer()->get(ReminderRepository::class);
        self::assertInstanceOf(ReminderRepository::class, $reminders);
        self::assertSame([], $reminders->dueEverywhere(self::mockTime('2026-09-10 09:06:00')->now(), 10));
    }

    public function testTheMessageCarriesTheLabelInItsSubject(): void
    {
        self::mockTime('2026-09-10 09:05:00');
        $recipient = $this->registerAccount('nicolas@focusyn.fr');

        ReminderFactory::new()->forRecipient($recipient)->about('Relire la synthèse Sommeil')->dueAt('2026-09-10 09:00')->create();
        $this->watch();

        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('Rappel : Relire la synthèse Sommeil', $message->getSubject());
    }

    private function watch(): int
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);

        $sent = $bus->dispatch(new NotifyDueReminders());
        self::assertIsInt($sent);

        return $sent;
    }

    private function registerAccount(string $email): RecipientId
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);
        $bus->dispatch(new RegisterUser($email, 'une phrase de passe tenable'));

        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString($email));
        self::assertNotNull($user);

        return RecipientId::fromString($user->id()->toString());
    }
}
